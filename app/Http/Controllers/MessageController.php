<?php

namespace App\Http\Controllers;

use App\Models\Conversation;
use App\Models\Media;
use App\Models\Message;
use App\Models\User;
use App\Support\ImageProcessor;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class MessageController extends Controller
{
    /** Returns the inbox, or redirects into the new thread when ?to= is given. */
    public function index(Request $request): Response|RedirectResponse
    {
        $user = $request->user();

        $conversations = Conversation::whereHas('participants', fn ($q) => $q->where('users.id', $user->id))
            ->with(['participants', 'messages' => fn ($q) => $q->latest()->limit(1)])
            ->latest('last_message_at')
            ->get()
            ->map(function (Conversation $c) use ($user) {
                $other = $c->participants->firstWhere('id', '!=', $user->id);
                $last = $c->messages->first();

                return [
                    'id' => $c->id,
                    'with' => $other ? [
                        'id' => $other->id,
                        'name' => $other->name,
                        'username' => $other->username,
                        'avatar' => $other->avatarUrl(),
                    ] : null,
                    'preview' => $last?->body ?? '',
                    'last_message_human' => $c->last_message_at?->diffForHumans(short: true),
                ];
            })
            ->values();

        // Starting a new message from a profile: find or create the conversation.
        if ($request->filled('to')) {
            $other = User::where('username', $request->query('to'))->firstOrFail();
            if ($other->id !== $user->id) {
                return redirect()->route('messages.show', Conversation::between($user->id, $other->id));
            }
        }

        return Inertia::render('Messages/Index', [
            'conversations' => $conversations,
            'activeId' => $request->query('conversation') ? (int) $request->query('conversation') : $conversations->first()['id'] ?? null,
        ]);
    }

    public function show(Request $request, Conversation $conversation): Response
    {
        $this->authorizeParticipant($request, $conversation);

        $conversation->load(['participants', 'messages.user', 'messages.media']);

        // mark read
        $conversation->participants()->updateExistingPivot($request->user()->id, ['last_read_at' => now()]);

        $other = $conversation->participants->firstWhere('id', '!=', $request->user()->id);

        return Inertia::render('Messages/Show', [
            'conversation' => [
                'id' => $conversation->id,
                'with' => $other ? [
                    'id' => $other->id,
                    'name' => $other->name,
                    'username' => $other->username,
                    'avatar' => $other->avatarUrl(),
                ] : null,
                'messages' => $conversation->messages->map(fn (Message $m) => [
                    'id' => $m->id,
                    'body' => $m->body,
                    'mine' => $m->user_id === $request->user()->id,
                    'created_at_human' => $m->created_at->diffForHumans(short: true),
                    'media' => $m->media ? (new \App\Http\Resources\MediaResource($m->media))->resolve() : null,
                ]),
            ],
            // so the thread header can jump straight to another chat
            'conversations' => $this->conversationList($request->user(), $conversation->id),
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'conversation_id' => ['nullable', 'integer', 'exists:conversations,id'],
            'to' => ['nullable', 'string', 'exists:users,username'],
            // a message needs text or an attachment, not both
            'body' => ['nullable', 'string', 'max:4000', 'required_without:file'],
            'file' => ['nullable', 'file', 'mimes:jpg,jpeg,png,webp,gif,mp4,webm', 'max:40960', 'required_without:body'],
        ]);

        $conversation = $data['conversation_id']
            ? Conversation::findOrFail($data['conversation_id'])
            : Conversation::between($request->user()->id, User::where('username', $data['to'])->value('id'));

        $this->authorizeParticipant($request, $conversation);

        $media = $request->hasFile('file') ? $this->storeAttachment($request->user(), $request->file('file')) : null;

        $conversation->messages()->create([
            'user_id' => $request->user()->id,
            'body' => $data['body'] ?? null,
            'media_id' => $media?->id,
            'rating' => 'sfw',
        ]);

        $conversation->forceFill(['last_message_at' => now()])->save();

        return redirect()->route('messages.show', $conversation);
    }

    /** Images and videos attached to a direct message. */
    private function storeAttachment(User $user, $file): Media
    {
        $disk = config('filesystems.upload_disk', 'public');
        $isVideo = str_starts_with((string) $file->getMimeType(), 'video/');

        // images are re-encoded so EXIF/GPS never reaches storage
        $path = $isVideo
            ? $file->store("uploads/{$user->id}/dm", $disk)
            : ImageProcessor::storeClean($file->getRealPath(), "uploads/{$user->id}/dm", $disk);

        [$width, $height] = ImageProcessor::dimensions($file->getRealPath());

        return Media::create([
            'user_id' => $user->id,
            'disk' => $disk,
            'path' => $path,
            'thumb_path' => $isVideo ? null : ImageProcessor::storeThumbnail($file->getRealPath(), "uploads/{$user->id}/dm/thumbs", $disk),
            'kind' => $isVideo ? 'video' : 'image',
            'width' => $width,
            'height' => $height,
            'size_bytes' => $file->getSize(),
            'status' => 'ready',
            'mime_type' => $file->getMimeType(),
        ]);
    }

    /** Inbox list for the thread header's conversation switcher. */
    private function conversationList(User $user, ?int $excludeId = null): array
    {
        return Conversation::whereHas('participants', fn ($q) => $q->where('users.id', $user->id))
            ->with(['participants', 'messages' => fn ($q) => $q->latest()->limit(1)])
            ->latest('last_message_at')
            ->take(50)
            ->get()
            ->map(function (Conversation $c) use ($user) {
                $other = $c->participants->firstWhere('id', '!=', $user->id);
                $last = $c->messages->first();

                return [
                    'id' => $c->id,
                    'with' => $other ? [
                        'name' => $other->name,
                        'username' => $other->username,
                        'avatar' => $other->avatarUrl(),
                    ] : null,
                    'preview' => $last?->body ?: ($last?->media_id ? 'Sent an attachment' : ''),
                    'last_message_human' => $c->last_message_at?->diffForHumans(short: true),
                ];
            })
            ->values()
            ->all();
    }

    private function authorizeParticipant(Request $request, Conversation $conversation): void
    {
        abort_unless(
            $conversation->participants()->whereKey($request->user()->id)->exists(),
            403,
            'You are not part of this conversation.'
        );
    }
}
