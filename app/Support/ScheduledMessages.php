<?php

namespace App\Support;

use App\Models\Conversation;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Symfony\Component\HttpKernel\Exception\HttpExceptionInterface;

class ScheduledMessages
{
    public static function deliverDue(): void
    {
        $due = DB::table('scheduled_messages')->where('status', 'pending')->where('send_at', '<=', now())
            ->whereIn('conversation_id', Conversation::forMessaging()->select('conversations.id'))
            ->orderBy('send_at')->orderBy('id')->limit(100)->get();

        foreach ($due as $snapshot) {
            try {
                self::deliver($snapshot);
            } catch (HttpExceptionInterface) {
                DB::table('scheduled_messages')->where('id', $snapshot->id)->where('status', 'pending')->update(['status' => 'failed']);
            }
        }
    }

    private static function deliver(object $snapshot): void
    {
        DB::transaction(function () use ($snapshot) {
            $viewer = User::find($snapshot->user_id);
            $chat = Conversation::find($snapshot->conversation_id);
            if (! $viewer || ! $chat) {
                return;
            }
            $other = $chat->kind === 'direct' ? $chat->participants()->where('users.id', '!=', $viewer->id)->first() : null;
            if ($other) {
                ChatAccess::lockAccounts($viewer, $other);
            }
            $chat = Conversation::lockForUpdate()->find($chat->id);
            $entry = DB::table('scheduled_messages')->where('id', $snapshot->id)->where('status', 'pending')->lockForUpdate()->first();
            if (! $chat || ! $entry) {
                return;
            }

            ChatAccess::requireMember($chat, $viewer);
            abort_unless($chat->request_status === 'accepted' && ChatAccess::canPost($chat, $viewer, $other), 403);
            $message = $chat->messages()->create([
                'user_id' => $viewer->id, 'body' => $entry->body, 'silent' => $entry->silent,
            ]);
            ChatAccess::messageSent($chat, $viewer, $message);
            DB::table('scheduled_messages')->where('id', $entry->id)->delete();
        });
    }
}
