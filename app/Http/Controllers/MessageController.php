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
            $this->rejectSelf($viewer, $other);
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
        $conversations = $this->conversationList($viewer, $filters);

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
        $readable = ChatAccess::readable($chat, $viewer);
        $pinned = (clone $readable)->whereNull('removed_at')->whereNotNull('pinned_at')
            ->latest('pinned_at')->first();

        $messages = (clone $readable)->with(['media', 'user', 'sharedPost.media.media', 'stickerPack'])
            ->when($before, fn ($query) => $query->where('messages.id', '<', $before))
            ->when($search !== null, fn ($query) => $query->whereNull('removed_at')->whereLike('body', $this->likePattern($search)))
            ->orderByDesc('messages.id')->limit(61)->get();
        $hasOlder = $messages->count() > 60;
        $messages = $messages->take(60)->reverse()->values();
        $ids = $messages->modelKeys();
        $replies = (clone $readable)->whereIn('messages.id', $messages->pluck('reply_to_id')->filter()->unique())
            ->with('user')->get()->keyBy('id');

        if (! $before && $search === null) {
            $lastRead = $messages->last()?->id ?? 0;
            DB::table('conversation_participants')->where('conversation_id', $chat->id)->where('user_id', $viewer->id)
                ->where(fn ($query) => $query->whereNull('last_read_message_id')->orWhere('last_read_message_id', '<', $lastRead))
                ->update(['last_read_message_id' => $lastRead, 'last_read_at' => now(), 'updated_at' => now()]);
            if ($member->marked_unread) {
                $chat->participants()->updateExistingPivot($viewer->id, ['marked_unread' => false]);
            }
        }

        $reactions = DB::table('message_reactions')->whereIn('message_id', $ids)
            ->selectRaw('message_id, emoji, count(*) as total, max(case when user_id = ? then 1 else 0 end) as mine', [$viewer->id])
            ->groupBy('message_id', 'emoji')->get()->groupBy('message_id');
        $choices = DB::table('message_choices')->whereIn('message_id', $messages->filter(fn ($message) => $message->interactive)->modelKeys())
            ->selectRaw('message_id, option_index, count(*) as total, max(case when user_id = ? then 1 else 0 end) as mine', [$viewer->id])
            ->groupBy('message_id', 'option_index')->get()->groupBy('message_id');
        $receipts = $other && Setting::enabled($viewer->id, 'chat_read_receipts', false)
            && Setting::enabled($other->id, 'chat_read_receipts', false) ? (int) $other->pivot->last_read_message_id : null;
        $canForwardOthers = ! $other || Setting::enabled($other->id, 'chat_allow_forwarding');
        $canSend = ChatAccess::canPost($chat, $viewer, $other);

        Inertia::encryptHistory();

        return Inertia::render('Messages/Show', [
            'conversation' => [
                'id' => $chat->id,
                'title' => $chat->title ?? $other?->name ?? 'Deleted account',
                'kind' => $chat->kind,
                'with' => $this->person($other),
                'canSend' => $canSend,
                'preferences' => $member->only(['pinned', 'archived', 'muted', 'folder']),
                'request' => $chat->kind === 'direct' && $chat->request_status !== 'accepted' ? [
                    'status' => $chat->request_status,
                    'mine' => (int) $chat->requested_by === $viewer->id,
                    'canAccept' => ChatAccess::canContact($viewer, $other),
                    'textOnly' => $chat->request_status === 'draft' && $other && ! ChatAccess::follows($other, $viewer),
                ] : null,
                'typing' => $chat->request_status === 'accepted' ? $chat->participants()->where('users.id', '!=', $viewer->id)
                    ->wherePivot('typing_until', '>', now())->limit(3)->pluck('users.name') : [],
                'readThrough' => $receipts,
                'pinned' => $pinned ? ['id' => $pinned->id, 'label' => $this->preview($pinned)] : null,
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
                    'media' => $this->attachmentResource($message),
                    'post' => $message->shared_post_id ? $this->sharedWork($message, $viewer) : null,
                    'reply' => $message->reply_to_id ? $this->replyPreview($replies->get($message->reply_to_id)) : null,
                    'reactions' => $reactions->get($message->id, collect())->values(),
                    'interactive' => $message->interactive ? $message->interactive + [
                        'votes' => $choices->get($message->id, collect())->values(),
                    ] : null,
                ]),
                'options' => $chat->only(['auto_delete_seconds']),
                'scheduled' => DB::table('scheduled_messages')->where('conversation_id', $chat->id)->where('user_id', $viewer->id)
                    ->whereIn('status', ['pending', 'failed'])->orderBy('send_at')->limit(30)->get(['id', 'body', 'send_at', 'status']),
                'hasOlder' => $hasOlder,
            ],
            'conversations' => fn () => array_slice($this->conversationList($viewer), 0, 50),
            'quickReplies' => fn () => json_decode(Setting::where('user_id', $viewer->id)
                ->where('key', 'chat_quick_replies')->value('value') ?? '[]', true) ?? [],
            'filters' => $filters,
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'conversation_id' => ['required_without:to', 'nullable', 'prohibits:to', 'integer', 'exists:conversations,id'],
            'to' => ['required_without:conversation_id', 'nullable', 'string', 'exists:users,username'],
            'body' => ['nullable', 'string', 'max:4000', 'required_without_all:file,shared_post_id,forward_id,interactive,sticker_post_id'],
            'file' => ['nullable', 'file', 'mimes:jpg,jpeg,png,webp,gif,mp4,webm,mp3,m4a,ogg,wav,opus,pdf,zip', 'max:40960'],
            'reply_to_id' => ['nullable', 'integer'],
            'shared_post_id' => ['nullable', 'integer', 'prohibits:forward_id'],
            'sticker_pack_id' => ['nullable', 'integer', 'required_with:sticker_post_id', 'prohibits:shared_post_id,forward_id'],
            'sticker_post_id' => ['nullable', 'integer', 'required_with:sticker_pack_id'],
            'forward_id' => ['nullable', 'integer', 'prohibits:file,interactive,shared_post_id'],
            'silent' => ['sometimes', 'boolean'],
            'spoiler' => ['sometimes', 'boolean'],
            'original' => ['sometimes', 'boolean'],
            'interactive' => ['nullable', 'array:type,title,options', 'prohibits:forward_id'],
            'interactive.type' => ['required_with:interactive', Rule::in(['poll', 'checklist'])],
            'interactive.title' => ['required_with:interactive', 'string', 'max:140'],
            'interactive.options' => ['required_with:interactive', 'array', 'list', 'min:2', 'max:10'],
            'interactive.options.*' => ['required', 'string', 'max:100', 'distinct'],
            'send_at' => ['nullable', 'date', 'after:now', 'before:+1 year', 'prohibits:file,forward_id,shared_post_id,reply_to_id,interactive,sticker_post_id'],
        ]);
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
            $this->rejectSelf($viewer, $other);
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
                    $following = ChatAccess::follows($other, $viewer);
                    if (! $following && ($request->hasFile('file')
                        || isset($data['forward_id']) || isset($data['shared_post_id']) || isset($data['interactive']) || isset($data['sticker_post_id'])
                        || isset($data['send_at']) || ($data['spoiler'] ?? false) || ($data['body'] ?? '') === '')) {
                        throw ValidationException::withMessages(['body' => 'A new message request must be text only.']);
                    }
                    if (isset($data['send_at'])) {
                        throw ValidationException::withMessages(['send_at' => 'Send a message before scheduling a new conversation.']);
                    }
                    $chat->update(['request_status' => $following ? 'accepted' : 'pending']);
                }

                if (isset($data['send_at'])) {
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
                if (isset($data['sticker_pack_id'], $data['sticker_post_id'])) {
                    $pack = StickerPack::findOrFail($data['sticker_pack_id']);
                    $postId = (int) $data['sticker_post_id'];
                    abort_unless(in_array($postId, array_map('intval', $pack->post_ids), true)
                        && StickerPack::eligible($viewer)->where('posts.user_id', $pack->user_id)->whereKey($postId)->exists(), 404);
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
                    abort_unless($this->visiblePost(Post::find($postId), $viewer), 404);
                }
                if ($request->hasFile('file')) {
                    $file = $request->file('file');
                    $attachment = MessageAttachments::store($viewer, $file, (bool) ($data['original'] ?? false));
                    $attachmentName = mb_substr(preg_replace('/[\x00-\x1F\x7F]/u', '', str_replace(['/', '\\'], '_', $file->getClientOriginalName())) ?? 'Attachment', 0, 150);
                }
                $message = $chat->messages()->create([
                    'user_id' => $viewer->id, 'body' => $body,
                    'media_id' => $attachment?->id ?? $forwardedMedia?->id,
                    'reply_to_id' => $reply?->id,
                    'shared_post_id' => $postId, 'forwarded_from' => $forwarded,
                    'silent' => $data['silent'] ?? false, 'interactive' => $data['interactive'] ?? null,
                    'spoiler' => $spoiler, 'attachment_name' => $attachmentName, 'sticker_pack_id' => $packId,
                ]);
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
        if (! in_array($mime, ['image/jpeg', 'image/png', 'image/webp', 'image/gif', 'video/mp4', 'video/webm',
            'audio/webm', 'audio/mpeg', 'audio/mp4', 'audio/x-m4a', 'audio/ogg', 'audio/wav', 'audio/x-wav', 'application/ogg'], true)) {
            $mime = 'application/octet-stream';
        }

        $response = response()->file($disk->path($path), [
            'Content-Type' => $mime, 'Cache-Control' => 'private, no-store', 'X-Content-Type-Options' => 'nosniff',
        ]);
        if ($media->kind === 'file') {
            $name = $message->attachment_name ?: 'Inkwell attachment';
            $fallback = str_replace(['%', '/', '\\'], '_', Str::ascii($name)) ?: 'Inkwell attachment';
            $response->setContentDisposition(ResponseHeaderBag::DISPOSITION_ATTACHMENT, $name, $fallback);
        }

        return $response;
    }

    private function attachmentResource(Message $message): ?array
    {
        if ($message->removed_at || ! $message->media || ! MessageAttachments::isPrivate($message->media)) {
            return null;
        }

        $media = (new MediaResource($message->media))->resolve();
        $media['url'] = route('messages.attachment', [$message, 'original'], false);
        $media['name'] = $message->attachment_name;
        $media['thumb'] = $message->media->thumb_path ? route('messages.attachment', [$message, 'thumb'], false) : null;

        return $media;
    }

    private function conversationList(User $viewer, array $filters = []): array
    {
        $tab = $filters['tab'] ?? 'all';
        $visible = Message::query()->whereColumn('messages.conversation_id', 'conversations.id')
            ->where(fn ($expiry) => ChatAccess::notExpired($expiry))
            ->whereNotExists(fn ($query) => $query->selectRaw('1')->from('message_hides')
                ->whereColumn('message_hides.message_id', 'messages.id')->where('user_id', $viewer->id));
        $unread = (clone $visible)->where('messages.user_id', '!=', $viewer->id);
        ChatAccess::unread($unread, 'membership');
        $incoming = fn ($query) => $query->where('request_status', 'pending')->where('requested_by', '!=', $viewer->id);
        $query = Conversation::forMessaging()->join('conversation_participants as membership', 'membership.conversation_id', '=', 'conversations.id')
            ->where('membership.user_id', $viewer->id)
            ->where(fn ($visibleDrafts) => $visibleDrafts->where('request_status', '!=', 'draft')->orWhere('requested_by', $viewer->id))
            ->where('membership.archived', $tab === 'archived')
            ->select('conversations.*', 'membership.marked_unread', 'membership.pinned', 'membership.muted')
            ->addSelect([
                'last_visible_id' => (clone $visible)->select('messages.id')->latest('messages.created_at')->orderByDesc('messages.id')->limit(1),
                'unread_count' => (clone $unread)->selectRaw('count(*)'),
            ])->orderByDesc('membership.pinned')->latest('conversations.last_message_at')->orderByDesc('conversations.id');

        if ($tab === 'requests') {
            $query->where($incoming);
        } elseif ($tab !== 'archived') {
            $query->whereNot($incoming);
        }
        if ($tab === 'direct') {
            $query->where('conversations.kind', $tab);
        }
        if ($tab === 'unread') {
            $query->where(fn ($filter) => $filter->where('membership.marked_unread', true)
                ->orWhereExists((clone $unread)->selectRaw('1')->toBase()));
        }
        if (isset($filters['folder'])) {
            $query->where('membership.folder', $filters['folder']);
        }
        if (isset($filters['q'])) {
            $like = $this->likePattern($filters['q']);
            $match = (clone $visible)->whereNull('removed_at')->whereLike('messages.body', $like);
            $query->addSelect(['matched_message_id' => (clone $match)->select('messages.id')->latest('messages.id')->limit(1)]);
            $query->where(fn ($search) => $search->whereLike('conversations.title', $like)
                ->orWhere(fn ($direct) => $direct->where('conversations.kind', 'direct')
                    ->whereHas('participants', fn ($people) => $people->where('users.id', '!=', $viewer->id)
                        ->where(fn ($name) => $name->whereLike('users.name', $like)->orWhereLike('users.username', $like))))
                ->orWhereExists((clone $match)->selectRaw('1')->toBase()));
        }

        $rows = $query->offset(((int) ($filters['page'] ?? 1) - 1) * 50)->limit(51)->get();
        $rows->filter(fn (Conversation $chat) => $chat->kind === 'direct')->load('participants');
        $messages = Message::whereKey($rows->pluck('last_visible_id')->filter()->all())->get()->keyBy('id');

        return $rows->map(function (Conversation $chat) use ($viewer, $messages) {
            $other = $chat->kind === 'direct' ? $chat->participants->firstWhere('id', '!=', $viewer->id) : null;
            $last = $messages->get($chat->last_visible_id);

            return [
                'id' => $chat->id, 'kind' => $chat->kind, 'with' => $this->person($other),
                'title' => $chat->title ?? $other?->name ?? 'Deleted account',
                'preview' => mb_strimwidth($this->preview($last), 0, 160, '…'),
                'last_message_human' => $last?->created_at->diffForHumans(short: true),
                'unread' => (int) $chat->unread_count,
                'markedUnread' => (bool) $chat->marked_unread, 'pinned' => (bool) $chat->pinned,
                'muted' => (bool) $chat->muted,
                'request' => $chat->request_status === 'pending',
                'searchMessages' => $last !== null && $chat->matched_message_id !== null,
            ];
        })->all();
    }

    private function preview(?Message $message): string
    {
        if ($message?->spoiler && ! $message->removed_at) {
            return 'Spoiler message';
        }

        return $message?->body ?? $message?->interactive['title'] ?? match (true) {
            (bool) $message?->media_id => 'Sent an attachment',
            (bool) $message?->shared_post_id => 'Shared an Inkwell work',
            default => '',
        };
    }

    private function replyPreview(?Message $message): ?array
    {
        return $message ? [
            'id' => $message->id,
            'sender' => $message->user?->name,
            'label' => mb_strimwidth($this->preview($message), 0, 100, '…'),
        ] : null;
    }

    private function sharedWork(Message $message, User $viewer): ?array
    {
        $post = $message->sharedPost;
        if (! $this->visiblePost($post, $viewer)) {
            return null;
        }

        $card = ['title' => $post->title ?: 'Inkwell work', 'url' => route('posts.show', $post, false)];
        $pack = $message->stickerPack;
        if ($pack && $post->user_id === $pack->user_id && $post->rating === 'sfw' && $post->is_original && $post->type === 'image') {
            return $card + [
                'sticker' => $post->cover()?->thumbUrl() ?: $post->cover()?->url(),
                'pack' => route('messages.stickers.index', ['pack' => $pack->id], false),
            ];
        }

        return $card;
    }

    private function visiblePost(?Post $post, User $viewer): bool
    {
        return $post && ($post->user_id === null || ! Block::existsBetween($viewer->id, $post->user_id))
            && ($post->rating !== 'explicit' || ($viewer->show_explicit && AgeGate::confirmed()));
    }

    private function likePattern(string $input): string
    {
        return '%'.$input.'%';
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
}
