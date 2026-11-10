<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Http\Controllers\MessageController as UserMessageController;
use App\Models\Conversation;
use App\Models\Message;
use App\Models\ModerationAction;
use App\Support\MessageAttachments;
use App\Support\MessagePayload;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;
use Inertia\Inertia;
use Symfony\Component\HttpFoundation\Response;

class MessageController extends Controller
{
    /** Search direct conversations by participant, text, attachment volume, and activity. */
    public function index(Request $request): Response
    {
        $filters = $request->validate([
            'q' => ['nullable', 'string', 'max:100'],
            'min_attachments' => ['nullable', 'integer', 'min:0', 'max:10000'],
            'min_media_mb' => ['nullable', 'numeric', 'min:0', 'max:100000'],
            'kind' => ['nullable', Rule::in(['image', 'video', 'audio', 'file'])],
            'sort' => ['nullable', Rule::in(['recent', 'media'])],
        ]);

        $stats = DB::table('messages as stats_messages')
            ->leftJoin('media as stats_media', 'stats_media.id', '=', 'stats_messages.media_id')
            ->whereNull('stats_messages.removed_at')
            ->where(fn ($q) => $q->whereNull('stats_messages.expires_at')->orWhere('stats_messages.expires_at', '>', now()))
            ->selectRaw('stats_messages.conversation_id, COUNT(*) AS message_count, COUNT(stats_media.id) AS attachment_count, COALESCE(SUM(stats_media.size_bytes), 0) AS media_bytes, MAX(stats_messages.created_at) AS latest_message_at')
            ->groupBy('stats_messages.conversation_id');

        $conversations = Conversation::query()
            ->joinSub($stats, 'message_stats', fn ($join) => $join->on('message_stats.conversation_id', '=', 'conversations.id'))
            ->where('conversations.kind', 'direct')
            ->select([
                'conversations.id', 'conversations.title', 'conversations.kind',
                'message_stats.message_count', 'message_stats.attachment_count', 'message_stats.media_bytes', 'message_stats.latest_message_at',
            ])
            ->with('participants:id,name,username')
            ->when(isset($filters['min_attachments']), fn ($q) => $q->where('message_stats.attachment_count', '>=', (int) $filters['min_attachments']))
            ->when(isset($filters['min_media_mb']), fn ($q) => $q->where('message_stats.media_bytes', '>=', (int) round((float) $filters['min_media_mb'] * 1024 * 1024)))
            ->when(isset($filters['q']), function ($q) use ($filters) {
                $term = MessagePayload::likePattern(trim($filters['q']));
                $q->where(function ($match) use ($term) {
                    $match->whereExists(fn ($messages) => $messages->selectRaw('1')->from('messages as matching_messages')
                        ->whereColumn('matching_messages.conversation_id', 'conversations.id')
                        ->whereNull('matching_messages.removed_at')
                        ->where(fn ($expiry) => $expiry->whereNull('matching_messages.expires_at')->orWhere('matching_messages.expires_at', '>', now()))
                        ->whereLike('matching_messages.body', $term))
                        ->orWhereHas('participants', fn ($participants) => $participants
                            ->whereLike('users.username', $term)->orWhereLike('users.name', $term));
                });
            })
            ->when(isset($filters['kind']), function ($q) use ($filters) {
                $q->whereExists(fn ($messages) => $messages->selectRaw('1')->from('messages as typed_messages')
                    ->join('media as typed_media', 'typed_media.id', '=', 'typed_messages.media_id')
                    ->whereColumn('typed_messages.conversation_id', 'conversations.id')
                    ->whereNull('typed_messages.removed_at')->where('typed_media.kind', $filters['kind'])
                    ->where(fn ($expiry) => $expiry->whereNull('typed_messages.expires_at')->orWhere('typed_messages.expires_at', '>', now())));
            })
            ->when(($filters['sort'] ?? 'recent') === 'media', fn ($q) => $q->orderByDesc('message_stats.media_bytes')->orderByDesc('message_stats.attachment_count'), fn ($q) => $q->orderByDesc('message_stats.latest_message_at'))
            ->orderByDesc('conversations.id')
            ->paginate(30)->withQueryString();

        ModerationAction::record($request->user(), 'search_messages', Conversation::class, null, null, null, [
            'query_used' => filled($filters['q'] ?? null),
            'min_attachments' => (int) ($filters['min_attachments'] ?? 0),
            'min_media_mb' => (float) ($filters['min_media_mb'] ?? 0),
            'kind' => $filters['kind'] ?? null,
            'sort' => $filters['sort'] ?? 'recent',
            'page' => $conversations->currentPage(),
        ]);

        return Inertia::render('Admin/Messages/Index', [
            'conversations' => $conversations->getCollection()->map(fn (Conversation $conversation) => [
                'id' => $conversation->id,
                'participants' => $conversation->participants->map(fn ($user) => ['name' => $user->name, 'username' => $user->username])->values(),
                'message_count' => (int) $conversation->message_count,
                'attachment_count' => (int) $conversation->attachment_count,
                'media_bytes' => (int) $conversation->media_bytes,
                'latest_message_at' => $conversation->latest_message_at,
            ])->values(),
            'filters' => [
                'q' => $filters['q'] ?? '',
                'min_attachments' => $filters['min_attachments'] ?? '',
                'min_media_mb' => $filters['min_media_mb'] ?? '',
                'kind' => $filters['kind'] ?? '',
                'sort' => $filters['sort'] ?? 'recent',
            ],
            'total' => $conversations->total(),
            'previousPage' => $conversations->previousPageUrl(),
            'nextPage' => $conversations->nextPageUrl(),
        ])->toResponse($request)->header('Cache-Control', 'private, no-store');
    }

    /** Deliberately open a conversation; this does not mark it read for participants. */
    public function show(Request $request, Conversation $conversation): Response
    {
        abort_unless($conversation->kind === 'direct', 404);
        $filters = $request->validate(['q' => ['nullable', 'string', 'max:100']]);
        $messages = Message::query()->where('conversation_id', $conversation->id)
            ->where(fn ($q) => $q->whereNull('expires_at')->orWhere('expires_at', '>', now()))
            ->when(isset($filters['q']), fn ($q) => $q->whereNull('removed_at')->whereLike('body', MessagePayload::likePattern(trim($filters['q']))))
            ->with(['user:id,name,username', 'media'])
            ->orderByDesc('id')->paginate(50)->withQueryString();
        $participants = $conversation->participants()->orderBy('users.id')->get(['users.id', 'users.name', 'users.username']);

        ModerationAction::record($request->user(), 'view_messages', Conversation::class, $conversation->id, null, null, [
            'participants' => $participants->pluck('username')->all(),
            'messages_shown' => $messages->count(),
            'query_used' => filled($filters['q'] ?? null),
            'page' => $messages->currentPage(),
        ]);
        Inertia::encryptHistory();

        return Inertia::render('Admin/Messages/Show', [
            'conversation' => [
                'id' => $conversation->id,
                'participants' => $participants->map(fn ($user) => ['name' => $user->name, 'username' => $user->username])->values(),
            ],
            'messages' => $messages->getCollection()->map(fn (Message $message) => [
                'id' => $message->id,
                'sender' => $message->user ? ['name' => $message->user->name, 'username' => $message->user->username] : null,
                'body' => $message->removed_at ? null : $message->body,
                'removed' => $message->removed_at !== null,
                'created_at' => $message->created_at?->toIso8601String(),
                'media_group' => $message->media_group,
                'media' => $message->media && ! $message->removed_at ? [
                    'kind' => $message->media->kind,
                    'size_bytes' => (int) $message->media->size_bytes,
                    'name' => $message->attachment_name,
                    'url' => route('admin.messages.attachment', $message, false),
                ] : null,
                'shared_post_id' => $message->removed_at ? null : $message->shared_post_id,
            ])->values(),
            'filters' => ['q' => $filters['q'] ?? ''],
            'total' => $messages->total(),
            'previousPage' => $messages->previousPageUrl(),
            'nextPage' => $messages->nextPageUrl(),
        ])->toResponse($request)->header('Cache-Control', 'private, no-store');
    }

    /** Reveal private media only after an admin explicitly opens the attachment. */
    public function attachment(Request $request, Message $message, UserMessageController $messages): Response
    {
        abort_unless($message->removed_at === null && (! $message->expires_at || $message->expires_at->isFuture()), 404);
        ModerationAction::record($request->user(), 'view_message_attachment', Message::class, $message->id, null, null, [
            'conversation_id' => $message->conversation_id,
            'sender_id' => $message->user_id,
            'kind' => $message->media?->kind,
            'size_bytes' => $message->media?->size_bytes,
        ]);

        return $messages->attachmentResponse($message, 'original');
    }

    /** Redact a message and safely purge its private attachment when no longer referenced. */
    public function destroy(Request $request, Message $message): RedirectResponse
    {
        $data = $request->validate(['reason' => ['required', 'string', 'max:500']]);

        DB::transaction(function () use ($request, $message, $data) {
            $conversation = Conversation::lockForUpdate()->findOrFail($message->conversation_id);
            abort_unless($conversation->kind === 'direct', 404);
            $current = Message::lockForUpdate()->findOrFail($message->id);
            abort_if($current->removed_at !== null, 409, 'This message has already been removed.');

            $mediaId = $current->media_id;
            $senderId = $current->user_id;
            $current->update([
                'body' => '[deleted]', 'media_id' => null, 'shared_post_id' => null, 'interactive' => null,
                'forwarded_from' => null, 'reply_to_id' => null, 'spoiler' => false, 'attachment_name' => null,
                'sticker_pack_id' => null, 'pinned_at' => null, 'media_group' => null,
                'media_group_position' => null, 'removed_at' => now(),
            ]);
            Conversation::bumpMessageRevision($conversation->id);
            ModerationAction::record($request->user(), 'remove_message', Message::class, $current->id, null, $data['reason'], [
                'conversation_id' => $conversation->id,
                'sender_id' => $senderId,
                'had_attachment' => $mediaId !== null,
            ]);
            MessageAttachments::purgeAfterCommit($mediaId);
        });

        return back()->with('success', 'Message removed.');
    }
}
