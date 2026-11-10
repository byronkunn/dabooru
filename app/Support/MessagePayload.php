<?php

namespace App\Support;

use App\Http\Resources\MediaResource;
use App\Models\Block;
use App\Models\Conversation;
use App\Models\Message;
use App\Models\Post;
use App\Models\User;
use Illuminate\Database\Eloquent\Relations\Pivot;
use Illuminate\Validation\ValidationException;

/**
 * Builds the JSON the messaging UI renders.
 *
 * Split out of MessageController, which had grown past 590 lines by mixing
 * request handling with payload shaping. This class only answers "what should
 * this look like" — it never writes.
 */
class MessagePayload
{
    public static function attachmentResource(Message $message): ?array
    {
        if ($message->removed_at || ! $message->media || ! MessageAttachments::isPrivate($message->media)) {
            return null;
        }

        $media = (new MediaResource($message->media))->resolve();
        $media['url'] = route('messages.attachment', [$message, 'original'], false);
        $media['name'] = $message->attachment_name;
        $media['thumb'] = $message->media->thumb_path ? route('messages.attachment', [$message, 'thumb'], false) : null;
        if ($message->sticker_pack_id) {
            $media['thumb'] = null;
            $media['sticker'] = true;
        }

        return $media;
    }

    public static function conversationList(User $viewer, array $filters = []): array
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
            $like = self::likePattern($filters['q']);
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
                'id' => $chat->id, 'kind' => $chat->kind, 'with' => self::person($other),
                'title' => $chat->title ?? $other?->name ?? 'Deleted account',
                'preview' => mb_strimwidth(self::preview($last), 0, 160, '…'),
                'last_message_human' => $last?->created_at->diffForHumans(short: true),
                'unread' => (int) $chat->unread_count,
                'markedUnread' => (bool) $chat->marked_unread, 'pinned' => (bool) $chat->pinned,
                'muted' => (bool) $chat->muted,
                'request' => $chat->request_status === 'pending',
                'searchMessages' => $last !== null && $chat->matched_message_id !== null,
            ];
        })->all();
    }

    public static function preview(?Message $message): string
    {
        if ($message?->spoiler && ! $message->removed_at) {
            return 'Spoiler message';
        }

        return $message?->body ?? $message?->interactive['title'] ?? match (true) {
            (bool) $message?->sticker_pack_id => 'Sent a sticker',
            (bool) $message?->media_id => 'Sent an attachment',
            (bool) $message?->shared_post_id => 'Shared an BooruHub work',
            default => '',
        };
    }

    public static function replyPreview(?Message $message): ?array
    {
        return $message ? [
            'id' => $message->id,
            'sender' => $message->user?->name,
            'label' => mb_strimwidth(self::preview($message), 0, 100, '…'),
        ] : null;
    }

    public static function sharedWork(Message $message, User $viewer): ?array
    {
        $post = $message->sharedPost;
        if (! self::visiblePost($post, $viewer)) {
            return null;
        }

        $card = ['title' => $post->title ?: 'BooruHub work', 'url' => route('posts.show', $post, false)];
        $pack = $message->stickerPack;
        if ($pack && $post->user_id === $pack->user_id && $post->rating === 'sfw' && $post->is_original && $post->type === 'image') {
            return $card + [
                'sticker' => $post->cover()?->thumbUrl() ?: $post->cover()?->url(),
                'pack' => route('messages.stickers.index', ['pack' => $pack->id], false),
            ];
        }

        return $card;
    }

    public static function visiblePost(?Post $post, User $viewer): bool
    {
        return $post && ($post->user_id === null || ! Block::existsBetween($viewer->id, $post->user_id))
            && ($post->rating !== 'explicit' || ($viewer->show_explicit && AgeGate::confirmed()));
    }

    public static function likePattern(string $input): string
    {
        return '%'.$input.'%';
    }

    public static function person(?User $user): ?array
    {
        return $user ? $user->only(['id', 'name', 'username']) + ['avatar' => $user->avatarUrl()] : null;
    }

    public static function rejectSelf(User $viewer, User $other): void
    {
        if ($viewer->id === $other->id) {
            throw ValidationException::withMessages(['to' => 'You cannot message yourself.']);
        }
    }
}
