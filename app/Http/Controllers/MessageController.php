<?php

namespace App\Http\Controllers;

use App\Http\Resources\MediaResource;
use App\Models\Block;
use App\Models\Conversation;
use App\Models\Media;
use App\Models\Message;
use App\Models\Post;
use App\Models\Setting;
use App\Models\StickerPack;
use App\Models\User;
use App\Support\AgeGate;
use App\Support\ChatAccess;
use App\Support\MessageAttachments;
use App\Support\MessagePayload;
use Illuminate\Database\Eloquent\Relations\Pivot;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;
use Inertia\Inertia;
use Inertia\Response;
use Symfony\Component\HttpFoundation\BinaryFileResponse;
use Symfony\Component\HttpFoundation\ResponseHeaderBag;
use Throwable;

class MessageController extends Controller
{
    public function index(Request $request): Response|RedirectResponse
    {
        $viewer = $request->user();

        if ($request->filled('to')) {
            $data = $request->validate(['to' => ['required', 'string']]);
            $other = User::where('username', $data['to'])->firstOrFail();
            MessagePayload::rejectSelf($viewer, $other);
            $thread = DB::transaction(fn () => ChatAccess::direct($viewer, $other));

            return redirect()->route('messages.show', $thread);
        }

        $filters = $request->validate([
            'q' => ['nullable', 'string', 'max:100'],
            'folder' => ['nullable', 'string', 'max:40'],
            'page' => ['nullable', 'integer', 'min:1', 'max:1000'],
        ]);
        $tab = $request->query('tab');
        if (in_array($tab, ['all', 'direct', 'requests', 'unread', 'archived'], true)) {
            $filters['tab'] = $tab;
        }
        $page = (int) ($filters['page'] ?? 1);
        $conversations = MessagePayload::conversationList($viewer, $filters);

        Inertia::encryptHistory();

        return Inertia::render('Messages/Index', [
            'conversations' => array_slice($conversations, 0, 50),
            'activeId' => $conversations[0]['id'] ?? null,
            'filters' => $filters,
            'hasMore' => count($conversations) > 50,
            'requestCount' => fn () => Conversation::where('kind', 'direct')
                ->join('conversation_participants as member', 'member.conversation_id', '=', 'conversations.id')
                ->where('member.user_id', $viewer->id)->where('member.archived', false)
                ->where('request_status', 'pending')->where('requested_by', '!=', $viewer->id)->count(),
            'page' => $page,
            'folders' => DB::table('conversation_participants')->where('user_id', $viewer->id)
                ->whereIn('conversation_id', Conversation::forMessaging()->select('conversations.id'))
                ->whereNotNull('folder')->distinct()->orderBy('folder')->pluck('folder'),
            'privacy' => [
                'chat_dm_permission' => ChatAccess::permission($viewer),
                'chat_read_receipts' => Setting::enabled($viewer->id, 'chat_read_receipts', false),
                'chat_allow_forwarding' => Setting::enabled($viewer->id, 'chat_allow_forwarding'),
            ],
        ]);
    }

    public function show(Request $request, Conversation $conversation): Response
    {
        $viewer = $request->user();
        $member = ChatAccess::requireMember($conversation, $viewer);
        $filters = $request->validate([
            'before' => ['nullable', 'integer', 'min:1'],
            'q' => ['nullable', 'string', 'max:100'],
        ]);
        $before = $filters['before'] ?? null;
        $search = $filters['q'] ?? null;
        $chat = $conversation;
        $other = $chat->kind === 'direct' ? $chat->participants()->where('users.id', '!=', $viewer->id)->first() : null;
        $state = $this->threadState($chat, $viewer, $member, $other);
        $readable = ChatAccess::readable($chat, $viewer);
        $pinned = (clone $readable)->whereNull('removed_at')->whereNotNull('pinned_at')
            ->latest('pinned_at')->first();

        $messages = (clone $readable)->with(['media', 'user', 'sharedPost.media.media', 'stickerPack'])
            ->when($before, fn ($query) => $query->where('messages.id', '<', $before))
            ->when($search !== null, fn ($query) => $query->whereNull('removed_at')->whereLike('body', MessagePayload::likePattern($search)))
            ->orderByDesc('messages.id')->limit(61)->get();
        $hasOlder = $messages->count() > 60;
        $messages = $messages->take(60)->reverse()->values();
        $ids = $messages->modelKeys();
        $replies = (clone $readable)->whereIn('messages.id', $messages->pluck('reply_to_id')->filter()->unique())
            ->with('user')->get()->keyBy('id');

        $reactions = DB::table('message_reactions')->whereIn('message_id', $ids)
            ->selectRaw('message_id, emoji, count(*) as total, max(case when user_id = ? then 1 else 0 end) as mine', [$viewer->id])
            ->groupBy('message_id', 'emoji')->get()->groupBy('message_id');
        $choices = DB::table('message_choices')->whereIn('message_id', $messages->filter(fn ($message) => $message->interactive)->modelKeys())
            ->selectRaw('message_id, option_index, count(*) as total, max(case when user_id = ? then 1 else 0 end) as mine', [$viewer->id])
            ->groupBy('message_id', 'option_index')->get()->groupBy('message_id');
        $canForwardOthers = ! $other || Setting::enabled($other->id, 'chat_allow_forwarding');

        Inertia::encryptHistory();

        return Inertia::render('Messages/Show', [
            'conversation' => [
                'id' => $chat->id,
                'title' => $chat->title ?? $other?->name ?? 'Deleted account',
                'kind' => $chat->kind,
                'with' => MessagePayload::person($other),
                'canSend' => $state['canSend'],
                'readPosition' => (int) $member->last_read_message_id,
                'markedUnread' => (bool) $member->marked_unread,
                'syncToken' => $this->syncToken($state),
                'preferences' => $member->only(['pinned', 'archived', 'muted', 'folder']),
                'request' => $chat->kind === 'direct' && $chat->request_status !== 'accepted' ? [
                    'status' => $chat->request_status,
                    'mine' => (int) $chat->requested_by === $viewer->id,
                    'canAccept' => $state['canAccept'],
                    'textOnly' => $chat->request_status === 'draft',
                ] : null,
                'typing' => $state['typing'],
                'readThrough' => $state['readThrough'],
                'pinned' => $pinned ? ['id' => $pinned->id, 'label' => MessagePayload::preview($pinned)] : null,
                'messages' => $messages->map(fn (Message $message) => [
                    'id' => $message->id,
                    'body' => $message->body,
                    'mine' => $message->user_id === $viewer->id,
                    'sender' => $message->user?->name,
                    'created_at_human' => $message->created_at->diffForHumans(short: true),
                    'expires' => $message->expires_at !== null,
                    'edited' => $message->edited_at !== null,
                    'removed' => $message->removed_at !== null,
                    'pinned' => $message->pinned_at !== null,
                    'forwarded_from' => $message->forwarded_from,
                    'canForward' => ! $message->expires_at && ! $message->interactive
                        && ($message->user_id === $viewer->id || $canForwardOthers),
                    'spoiler' => $message->spoiler,
                    'media' => MessagePayload::attachmentResource($message),
                    'media_group' => $message->media_group,
                    'media_group_position' => $message->media_group_position,
                    'post' => $message->shared_post_id ? MessagePayload::sharedWork($message, $viewer) : null,
                    'reply' => $message->reply_to_id ? MessagePayload::replyPreview($replies->get($message->reply_to_id)) : null,
                    'reactions' => $reactions->get($message->id, collect())->values(),
                    'interactive' => $message->interactive ? $message->interactive + [
                        'votes' => $choices->get($message->id, collect())->values(),
                        // who resolved an inline-button message, for display
                        'answered_by_name' => ($message->interactive['answered_by'] ?? null)
                            ? \App\Models\User::whereKey($message->interactive['answered_by'])->value('name')
                            : null,
                        'answer_label' => isset($message->interactive['answer_index'])
                            ? ($message->interactive['options'][$message->interactive['answer_index']] ?? null)
                            : null,
                    ] : null,
                ]),
                'options' => $chat->only(['auto_delete_seconds']),
                'scheduled' => DB::table('scheduled_messages')->where('conversation_id', $chat->id)->where('user_id', $viewer->id)
                    ->whereIn('status', ['pending', 'failed'])->orderBy('send_at')->limit(30)->get(['id', 'body', 'send_at', 'status']),
                'hasOlder' => $hasOlder,
            ],
            'conversations' => fn () => array_slice(MessagePayload::conversationList($viewer), 0, 50),
            'quickReplies' => fn () => json_decode(Setting::where('user_id', $viewer->id)
                ->where('key', 'chat_quick_replies')->value('value') ?? '[]', true) ?? [],
            'filters' => $filters,
        ]);
    }

    public function sync(Request $request, Conversation $conversation): JsonResponse
    {
        $viewer = $request->user();
        $member = ChatAccess::requireMember($conversation, $viewer);
        $other = $conversation->kind === 'direct' ? $conversation->participants()->whereKeyNot($viewer->id)->first() : null;

        return response()->json([
            'token' => $this->syncToken($this->threadState($conversation, $viewer, $member, $other)),
        ])->header('Cache-Control', 'private, no-store');
    }

    private function threadState(Conversation $chat, User $viewer, Pivot $member, ?User $other): array
    {
        $presence = $chat->request_status === 'accepted' && ChatAccess::canContact($viewer, $other);

        return [
            'revision' => (int) $chat->message_revision,
            'inboxRevision' => Conversation::forMessaging()
                ->join('conversation_participants', 'conversation_participants.conversation_id', '=', 'conversations.id')
                ->where('conversation_participants.user_id', $viewer->id)->sum('conversations.message_revision'),
            'status' => $chat->request_status,
            'canSend' => ChatAccess::canPost($chat, $viewer, $other),
            'canAccept' => $chat->request_status === 'pending' && ChatAccess::canContact($viewer, $other),
            'typing' => $presence && $other->pivot->typing_until > now()->toDateTimeString() ? [$other->name] : [],
            'readThrough' => $presence && Setting::enabled($viewer->id, 'chat_read_receipts', false)
                && Setting::enabled($other->id, 'chat_read_receipts', false) ? (int) $other->pivot->last_read_message_id : null,
            'readPosition' => (int) $member->last_read_message_id,
            'markedUnread' => (bool) $member->marked_unread,
            'preferences' => $member->only(['pinned', 'archived', 'muted', 'folder']),
            'expires' => $chat->messages()->where('expires_at', '<=', now())->reorder()->count(),
            'timer' => (int) $chat->auto_delete_seconds,
        ];
    }

    private function syncToken(array $state): string
    {
        return hash('sha256', json_encode($state, JSON_THROW_ON_ERROR));
    }

    public function store(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'conversation_id' => ['required_without:to', 'nullable', 'prohibits:to', 'integer', 'exists:conversations,id'],
            'to' => ['required_without:conversation_id', 'nullable', 'string', 'exists:users,username'],
            'body' => ['nullable', 'string', 'max:4000', 'required_without_all:file,shared_post_id,forward_id,interactive,sticker_post_id,sticker_upload_id'],
            'file' => ['nullable', 'file', 'mimes:jpg,jpeg,png,webp,gif,mp4,webm,mp3,m4a,ogg,wav,opus,pdf,zip', 'max:40960'],
            // extra files that travel with the first one as a single album message
            'files' => ['nullable', 'array', 'max:9'],
            'files.*' => ['file', 'mimes:jpg,jpeg,png,webp,gif,mp4,webm', 'max:40960'],
            'reply_to_id' => ['nullable', 'integer'],
            'shared_post_id' => ['nullable', 'integer', 'prohibits:forward_id'],
            'sticker_pack_id' => ['nullable', 'integer', 'required_with:sticker_post_id,sticker_upload_id', 'prohibits:shared_post_id,forward_id,file,interactive'],
            'sticker_post_id' => ['nullable', 'integer', 'prohibits:sticker_upload_id'],
            'sticker_upload_id' => ['nullable', 'integer'],
            'forward_id' => ['nullable', 'integer', 'prohibits:file,interactive,shared_post_id'],
            'silent' => ['sometimes', 'boolean'],
            'spoiler' => ['sometimes', 'boolean'],
            'original' => ['sometimes', 'boolean'],
            'interactive' => ['nullable', 'array:type,title,options', 'prohibits:forward_id'],
            // Telegram-style inline keyboard: 2-4 buttons that resolve the message
            'interactive.type' => ['required_with:interactive', Rule::in(['poll', 'checklist', 'buttons'])],
            'interactive.title' => ['required_with:interactive', 'string', 'max:140'],
            'interactive.options' => ['required_with:interactive', 'array', 'list', 'min:1', 'max:10'],
            'interactive.options.*' => ['required', 'string', 'max:100', 'distinct'],
            'send_at' => ['nullable', 'date', 'after:now', 'before:+1 year', 'prohibits:file,forward_id,shared_post_id,reply_to_id,interactive,sticker_post_id,sticker_upload_id'],
        ]);
        // a poll needs at least two answers; inline buttons can be a single action
        if (($data['interactive']['type'] ?? null) === 'poll' && count($data['interactive']['options']) < 2) {
            throw ValidationException::withMessages(['interactive.options' => 'A poll needs at least two options.']);
        }

        $viewer = $request->user();
        $thread = isset($data['conversation_id']) ? Conversation::findOrFail($data['conversation_id']) : null;
        $other = null;
        if (! $thread || $thread->kind === 'direct') {
            if ($thread) {
                ChatAccess::requireMember($thread, $viewer);
            }
            $other = $thread ? $thread->participants()->where('users.id', '!=', $viewer->id)->first()
                : User::where('username', $data['to'])->firstOrFail();
            abort_unless($other, 403, 'This conversation is unavailable.');
            MessagePayload::rejectSelf($viewer, $other);
        }

        $attachment = null;
        try {
            $conversation = DB::transaction(function () use ($request, $viewer, $other, $thread, $data, &$attachment) {
                if ($other) {
                    ChatAccess::lockAccounts($viewer, $other);
                }
                $chat = $thread ? Conversation::lockForUpdate()->findOrFail($thread->id) : ChatAccess::direct($viewer, $other);
                ChatAccess::requireMember($chat, $viewer);
                abort_unless(ChatAccess::canPost($chat, $viewer, $other), 403, 'Sending is unavailable for this conversation.');

                if ($chat->request_status === 'draft') {
                    if ($request->hasFile('file')
                        || isset($data['forward_id']) || isset($data['shared_post_id']) || isset($data['interactive']) || isset($data['sticker_post_id']) || isset($data['sticker_upload_id'])
                        || isset($data['reply_to_id']) || isset($data['send_at']) || ($data['spoiler'] ?? false)
                        || ($data['silent'] ?? false) || trim($data['body'] ?? '') === '') {
                        throw ValidationException::withMessages(['body' => 'A new message request must be text only.']);
                    }
                    $chat->update(['request_status' => 'pending', 'requested_by' => $viewer->id]);
                }

                if (isset($data['send_at'])) {
                    if ($chat->request_status === 'declined') {
                        throw ValidationException::withMessages(['send_at' => 'Send a message to reopen this chat before scheduling.']);
                    }
                    if ($data['spoiler'] ?? false) {
                        throw ValidationException::withMessages(['send_at' => 'Scheduled messages support plain text.']);
                    }
                    if (DB::table('scheduled_messages')->where('user_id', $viewer->id)->where('status', 'pending')
                        ->whereIn('conversation_id', Conversation::forMessaging()->select('conversations.id'))->count() >= 30) {
                        throw ValidationException::withMessages(['send_at' => 'You can have up to 30 scheduled messages.']);
                    }
                    DB::table('scheduled_messages')->insert([
                        'conversation_id' => $chat->id, 'user_id' => $viewer->id,
                        'body' => $data['body'], 'silent' => $data['silent'] ?? false,
                        'send_at' => Carbon::parse($data['send_at'])->utc(),
                    ]);
                    Conversation::bumpMessageRevision($chat->id);

                    return $chat;
                }

                $reply = isset($data['reply_to_id'])
                    ? ChatAccess::readable($chat, $viewer)->whereKey($data['reply_to_id'])->whereNull('removed_at')->firstOrFail() : null;
                $postId = $data['shared_post_id'] ?? null;
                $body = $data['body'] ?? null;
                $forwarded = null;
                $forwardedMedia = null;
                $spoiler = $data['spoiler'] ?? false;
                $attachmentName = null;
                $packId = null;
                if (isset($data['sticker_pack_id'])) {
                    $pack = StickerPack::availableTo($viewer)->lockForUpdate()->findOrFail($data['sticker_pack_id']);
                    if (isset($data['sticker_upload_id'])) {
                        $sticker = $pack->stickers()->findOrFail($data['sticker_upload_id']);
                        $forwardedMedia = Media::lockForUpdate()->findOrFail($sticker->media_id);
                        $attachmentName = $sticker->title;
                    } else {
                        $postId = (int) ($data['sticker_post_id'] ?? 0);
                        abort_unless(in_array($postId, array_map('intval', $pack->post_ids), true)
                            && StickerPack::eligible($viewer)->where('posts.user_id', $pack->user_id)->whereKey($postId)->exists(), 404);
                    }
                    $packId = $pack->id;
                }

                if (isset($data['forward_id'])) {
                    $source = Message::with(['conversation', 'user'])->findOrFail($data['forward_id']);
                    ChatAccess::requireMessage($source, $viewer);
                    abort_unless(! $source->removed_at && ! $source->interactive && ! $source->expires_at
                        && $source->conversation->request_status === 'accepted'
                        && ($source->conversation->kind !== 'direct' || $source->user_id === $viewer->id
                            || Setting::enabled($source->user_id, 'chat_allow_forwarding')), 403);
                    $postId = $source->shared_post_id;
                    $packId = $source->sticker_pack_id;
                    $spoiler = $source->spoiler;
                    $attachmentName = $source->attachment_name;
                    $body = $source->body;
                    $forwardedMedia = $source->media_id ? Media::lockForUpdate()->find($source->media_id) : null;
                    $forwarded = $source->forwarded_from ?? $source->user?->name;
                }

                if ($postId) {
                    abort_unless(MessagePayload::visiblePost(Post::find($postId), $viewer), 404);
                }
                $album = [];
                if ($request->hasFile('file')) {
                    $file = $request->file('file');
                    $attachment = MessageAttachments::store($viewer, $file, (bool) ($data['original'] ?? false));
                    $attachmentName = mb_substr(preg_replace('/[\x00-\x1F\x7F]/u', '', str_replace(['/', '\\'], '_', $file->getClientOriginalName())) ?? 'Attachment', 0, 150);
                    $album[] = $attachment;
                }
                foreach ($request->file('files', []) as $extra) {
                    $album[] = MessageAttachments::store($viewer, $extra, (bool) ($data['original'] ?? false));
                }
                // several files become one album: a shared group id, one message each
                $groupId = count($album) > 1 ? (string) Str::uuid() : null;

                $message = $chat->messages()->create([
                    'user_id' => $viewer->id, 'body' => $body,
                    'media_id' => $attachment?->id ?? $forwardedMedia?->id,
                    'media_group' => $groupId,
                    'media_group_position' => $groupId ? 0 : null,
                    'reply_to_id' => $reply?->id,
                    'shared_post_id' => $postId, 'forwarded_from' => $forwarded,
                    'silent' => $data['silent'] ?? false, 'interactive' => $data['interactive'] ?? null,
                    'spoiler' => $spoiler, 'attachment_name' => $attachmentName, 'sticker_pack_id' => $packId,
                ]);
                if ($chat->request_status === 'declined') {
                    $chat->update(['request_status' => 'accepted']);
                    $chat->participants()->updateExistingPivot($viewer->id, ['archived' => false]);
                }
                if ($groupId) {
                    foreach (array_slice($album, 1) as $index => $media) {
                        $extra = $chat->messages()->create([
                            'user_id' => $viewer->id,
                            'body' => null,
                            'media_id' => $media->id,
                            'media_group' => $groupId,
                            'media_group_position' => $index + 1,
                            'reply_to_id' => $reply?->id,
                            'silent' => $data['silent'] ?? false,
                            'spoiler' => $spoiler,
                            'attachment_name' => null,
                        ]);
                        ChatAccess::messageSent($chat, $viewer, $extra);
                    }
                }

                ChatAccess::messageSent($chat, $viewer, $message);

                return $chat;
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
        ChatAccess::requireMessage($message, $request->user());

        return $this->attachmentResponse($message, $variant);
    }

    public function attachmentResponse(Message $message, string $variant): BinaryFileResponse
    {
        $media = $message->media;
        abort_unless(! $message->removed_at && $media && MessageAttachments::isPrivate($media), 404);
        $path = $variant === 'thumb' ? $media->thumb_path : $media->path;
        $disk = Storage::disk(MessageAttachments::DISK);
        abort_unless($path && str_starts_with($path, 'dm/') && $disk->exists($path), 404);

        $mime = $disk->mimeType($path);
        if ($media->kind === 'audio' && in_array($mime, ['video/mp4', 'video/webm'], true)) {
            $mime = str_replace('video/', 'audio/', $mime);
        }
        if (! in_array($mime, ['image/jpeg', 'image/png', 'image/webp', 'image/gif', 'image/avif', 'video/mp4', 'video/webm',
            'audio/webm', 'audio/mpeg', 'audio/mp4', 'audio/x-m4a', 'audio/ogg', 'audio/wav', 'audio/x-wav', 'application/ogg'], true)) {
            $mime = 'application/octet-stream';
        }

        $response = response()->file($disk->path($path), [
            'Content-Type' => $mime, 'Cache-Control' => 'private, no-store', 'X-Content-Type-Options' => 'nosniff',
        ]);
        if ($media->kind === 'file') {
            $name = $message->attachment_name ?: 'ModernBooru attachment';
            $fallback = str_replace(['%', '/', '\\'], '_', Str::ascii($name)) ?: 'ModernBooru attachment';
            $response->setContentDisposition(ResponseHeaderBag::DISPOSITION_ATTACHMENT, $name, $fallback);
        }

        return $response;
    }

}
