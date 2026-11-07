<?php

namespace App\Http\Controllers;

use App\Http\Resources\MediaResource;
use App\Models\Block;
use App\Models\Conversation;
use App\Models\Message;
use App\Models\User;
use App\Support\MessageAttachments;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\ValidationException;
use Inertia\Inertia;
use Inertia\Response;
use Symfony\Component\HttpFoundation\BinaryFileResponse;
use Throwable;

class MessageController extends Controller
{
    public function index(Request $request): Response|RedirectResponse
    {
        $viewer = $request->user();

        if ($request->filled('to')) {
            $other = User::where('username', $request->query('to'))->firstOrFail();
            $this->rejectSelf($viewer, $other);
            $thread = DB::transaction(function () use ($viewer, $other) {
                $this->authorizeSend($viewer, $other);

                return Conversation::between($viewer->id, $other->id);
            });

            return redirect()->route('messages.show', $thread);
        }

        $conversations = $this->conversationList($viewer);

        return Inertia::render('Messages/Index', [
            'conversations' => $conversations,
            'activeId' => $request->query('conversation')
                ? $request->integer('conversation') : ($conversations[0]['id'] ?? null),
        ]);
    }

    public function show(Request $request, Conversation $conversation): Response
    {
        $viewer = $request->user();
        $this->authorizeParticipant($request, $conversation);
        $conversation->load(['participants', 'messages.media']);

        // Advance to the last message actually loaded, without moving backward
        // if another request has already marked a newer snapshot read.
        $lastRead = $conversation->messages->max('id') ?? 0;
        DB::table('conversation_participants')
            ->where('conversation_id', $conversation->id)
            ->where('user_id', $viewer->id)
            ->where(fn ($query) => $query->whereNull('last_read_message_id')->orWhere('last_read_message_id', '<', $lastRead))
            ->update(['last_read_message_id' => $lastRead, 'last_read_at' => now(), 'updated_at' => now()]);

        $other = $conversation->participants->firstWhere('id', '!=', $viewer->id);

        return Inertia::render('Messages/Show', [
            'conversation' => [
                'id' => $conversation->id,
                'with' => $this->person($other),
                'canSend' => $this->canSend($viewer, $other),
                'messages' => $conversation->messages->map(fn (Message $message) => [
                    'id' => $message->id,
                    'body' => $message->body,
                    'mine' => $message->user_id === $viewer->id,
                    'created_at_human' => $message->created_at->diffForHumans(short: true),
                    'media' => $this->attachmentResource($message),
                ]),
            ],
            'conversations' => $this->conversationList($viewer, 50),
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'conversation_id' => ['required_without:to', 'nullable', 'prohibits:to', 'integer', 'exists:conversations,id'],
            'to' => ['required_without:conversation_id', 'nullable', 'string', 'exists:users,username'],
            'body' => ['nullable', 'string', 'max:4000', 'required_without:file'],
            'file' => ['nullable', 'file', 'mimes:jpg,jpeg,png,webp,gif,mp4,webm', 'max:40960', 'required_without:body'],
        ]);

        $viewer = $request->user();
        $thread = isset($data['conversation_id']) ? Conversation::findOrFail($data['conversation_id']) : null;

        if ($thread) {
            $this->authorizeParticipant($request, $thread);
            $other = $thread->participants()->where('users.id', '!=', $viewer->id)->first();
            abort_unless($other, 403, 'This conversation is unavailable.');
        } else {
            $other = User::where('username', $data['to'])->firstOrFail();
            $this->rejectSelf($viewer, $other);
        }

        $attachment = null;
        try {
            $conversation = DB::transaction(function () use ($request, $viewer, $other, $thread, $data, &$attachment) {
                $this->authorizeSend($viewer, $other);
                $conversation = $thread ? Conversation::findOrFail($thread->id) : Conversation::between($viewer->id, $other->id);
                $this->authorizeParticipant($request, $conversation);
                $attachment = $request->hasFile('file') ? MessageAttachments::store($viewer, $request->file('file')) : null;

                $message = $conversation->messages()->create([
                    'user_id' => $viewer->id,
                    'body' => $data['body'] ?? null,
                    'media_id' => $attachment?->id,
                    'rating' => 'sfw',
                ]);

                $conversation->forceFill(['last_message_at' => $message->created_at])->save();

                return $conversation;
            });
        } catch (Throwable $error) {
            if ($attachment) {
                rescue(fn () => Storage::disk(MessageAttachments::DISK)->deleteDirectory(dirname($attachment->path)));
            }

            throw $error;
        }

        return redirect()->route('messages.show', $conversation);
    }

    public function attachment(Request $request, Message $message, string $variant): BinaryFileResponse
    {
        $this->authorizeParticipant($request, $message->conversation);
        $media = $message->media;
        abort_unless($media && MessageAttachments::isPrivate($media), 404);
        $path = $variant === 'thumb' ? $media->thumb_path : $media->path;
        $disk = Storage::disk(MessageAttachments::DISK);
        abort_unless($path && str_starts_with($path, 'dm/') && $disk->exists($path), 404);

        $mime = $disk->mimeType($path);
        if (! in_array($mime, ['image/jpeg', 'image/png', 'image/webp', 'image/gif', 'video/mp4', 'video/webm'], true)) {
            $mime = 'application/octet-stream';
        }

        return response()->file($disk->path($path), [
            'Content-Type' => $mime,
            'Cache-Control' => 'private, no-store',
            'X-Content-Type-Options' => 'nosniff',
        ]);
    }

    private function attachmentResource(Message $message): ?array
    {
        if (! $message->media || ! MessageAttachments::isPrivate($message->media)) {
            return null;
        }

        $media = (new MediaResource($message->media))->resolve();
        $media['url'] = route('messages.attachment', [$message, 'original'], false);
        $media['thumb'] = $message->media->thumb_path ? route('messages.attachment', [$message, 'thumb'], false) : null;

        return $media;
    }

    private function conversationList(User $viewer, ?int $limit = null): array
    {
        return Conversation::whereHas('participants', fn ($query) => $query->where('users.id', $viewer->id))
            ->with(['participants', 'latestMessage'])
            ->latest('last_message_at')->orderByDesc('id')
            ->when($limit, fn ($query) => $query->limit($limit))
            ->get()->map(function (Conversation $conversation) use ($viewer) {
                $other = $conversation->participants->firstWhere('id', '!=', $viewer->id);
                $last = $conversation->latestMessage;

                return [
                    'id' => $conversation->id,
                    'with' => $this->person($other),
                    'preview' => $last?->body ?? ($last?->media_id ? 'Sent an attachment' : ''),
                    'last_message_human' => $conversation->last_message_at?->diffForHumans(short: true),
                ];
            })->all();
    }

    private function person(?User $user): ?array
    {
        return $user ? $user->only(['id', 'name', 'username']) + ['avatar' => $user->avatarUrl()] : null;
    }

    private function rejectSelf(User $viewer, User $other): void
    {
        if ($viewer->id === $other->id) {
            throw ValidationException::withMessages(['to' => 'You cannot message yourself.']);
        }
    }

    private function authorizeSend(User $viewer, User $other): void
    {
        $locked = User::whereKey([$viewer->id, $other->id])->orderBy('id')->lockForUpdate()->get()->keyBy('id');
        $sender = $locked->get($viewer->id);
        abort_unless($sender && $this->canSend($sender, $locked->get($other->id)), 403, 'You cannot message this account.');
    }

    private function canSend(User $viewer, ?User $other): bool
    {
        return $other && ! $viewer->isBanned() && ! $other->isBanned()
            && ! Block::existsBetween($viewer->id, $other->id);
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
