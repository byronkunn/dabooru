<?php

namespace App\Support;

use App\Models\Block;
use App\Models\Conversation;
use App\Models\Message;
use App\Models\Setting;
use App\Models\User;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Relations\Pivot;
use Illuminate\Support\Facades\DB;

class ChatAccess
{
    public static function membership(Conversation $chat, User $viewer): ?Pivot
    {
        if (! in_array($chat->kind, Conversation::MESSAGING_KINDS, true)
            || ($chat->request_status === 'draft' && (int) $chat->requested_by !== $viewer->id)) {
            return null;
        }

        return $chat->participants()->whereKey($viewer->id)->first()?->pivot;
    }

    public static function requireMember(Conversation $chat, User $viewer): Pivot
    {
        $member = self::membership($chat, $viewer);
        abort_unless($member, 403, 'You are not part of this conversation.');

        return $member;
    }

    public static function canContact(User $sender, ?User $recipient): bool
    {
        return $recipient && ! $sender->isBanned() && ! $recipient->isBanned()
            && ! Block::existsBetween($sender->id, $recipient->id);
    }

    public static function permission(User $recipient): string
    {
        return Setting::where('user_id', $recipient->id)->where('key', 'chat_dm_permission')->value('value') ?? 'anyone';
    }

    public static function follows(User $recipient, User $sender): bool
    {
        return $recipient->following()->whereKey($sender->id)->exists();
    }

    public static function permitsContact(User $recipient, User $sender): bool
    {
        return match (self::permission($recipient)) {
            'anyone' => true,
            'following' => self::follows($recipient, $sender),
            default => false,
        };
    }

    public static function direct(User $sender, User $recipient): Conversation
    {
        self::lockAccounts($sender, $recipient);
        abort_unless(self::canContact($sender, $recipient), 403, 'You cannot message this account.');

        $existing = Conversation::existingBetween($sender->id, $recipient->id);
        if ($existing && $existing->request_status !== 'draft') {
            return $existing;
        }

        abort_unless(self::permitsContact($recipient, $sender), 403, 'This account limits new conversations.');
        $chat = $existing ?? Conversation::between($sender->id, $recipient->id);
        if (! $existing || (int) $chat->requested_by !== $sender->id) {
            $chat->update(['request_status' => 'draft', 'requested_by' => $sender->id]);
        }

        return $chat;
    }

    public static function lockAccounts(User $first, User $second): void
    {
        $accounts = User::whereKey([$first->id, $second->id])->orderBy('id')->lockForUpdate()->get()->keyBy('id');
        abort_unless($accounts->has($first->id) && $accounts->has($second->id), 403);
        abort_if($accounts->get($first->id)->isBanned() || $accounts->get($second->id)->isBanned(), 403);
    }

    public static function canPost(Conversation $chat, User $viewer, ?User $other): bool
    {
        if ($viewer->isBanned()) {
            return false;
        }
        if ($chat->kind === 'direct') {
            return in_array($chat->request_status, ['accepted', 'draft'], true)
                && self::canContact($viewer, $other)
                && ($chat->request_status === 'accepted' || self::permitsContact($other, $viewer));
        }

        return $chat->kind === 'saved';
    }

    public static function canInteract(Conversation $chat, User $viewer): bool
    {
        return ! $viewer->isBanned() && $chat->request_status === 'accepted'
            && ($chat->kind !== 'direct' || self::canContact($viewer, $chat->participants()->whereKeyNot($viewer->id)->first()));
    }

    public static function readable(Conversation $chat, User $viewer): Builder
    {
        return Message::where('messages.conversation_id', $chat->id)
            ->where(fn ($query) => self::notExpired($query))
            ->whereNotExists(fn ($query) => $query->selectRaw('1')->from('message_hides')
                ->whereColumn('message_hides.message_id', 'messages.id')->where('user_id', $viewer->id));
    }

    public static function requireMessage(Message $message, User $viewer): void
    {
        $chat = $message->conversation;
        self::requireMember($chat, $viewer);
        abort_unless(self::readable($chat, $viewer)->whereKey($message->id)->exists(), 404);
    }

    public static function messageSent(Conversation $chat, User $viewer, Message $message): void
    {
        if ($chat->auto_delete_seconds) {
            $message->update(['expires_at' => $message->created_at->copy()->addSeconds($chat->auto_delete_seconds)]);
        }
        $chat->update(['last_message_at' => $message->created_at]);
        $chat->participants()->updateExistingPivot($viewer->id, ['typing_until' => null]);
    }

    public static function notExpired($query): void
    {
        $query->whereNull('messages.expires_at')->orWhere('messages.expires_at', '>', now());
    }

    public static function unread($query, string $membership = 'conversation_participants'): void
    {
        $query->whereNull('messages.removed_at')->where(fn ($expiry) => self::notExpired($expiry))
            ->whereNotExists(fn ($hidden) => $hidden->selectRaw('1')->from('message_hides')
                ->whereColumn('message_hides.message_id', 'messages.id')->whereColumn('message_hides.user_id', "$membership.user_id"))
            ->where(fn ($new) => $new->whereColumn('messages.id', '>', "$membership.last_read_message_id")
                ->orWhere(fn ($legacy) => $legacy->whereNull("$membership.last_read_message_id")
                    ->where(fn ($time) => $time->whereNull("$membership.last_read_at")
                        ->orWhereColumn('messages.created_at', '>', "$membership.last_read_at"))));
    }

    public static function unreadCount(User $viewer): int
    {
        $query = DB::table('messages')->join('conversation_participants', 'conversation_participants.conversation_id', '=', 'messages.conversation_id')
            ->whereIn('messages.conversation_id', Conversation::forMessaging()->select('conversations.id'))
            ->where('conversation_participants.user_id', $viewer->id)
            ->where('conversation_participants.muted', false)->where('messages.user_id', '!=', $viewer->id)
            ->where('messages.silent', false);
        self::unread($query);

        return $query->count();
    }
}
