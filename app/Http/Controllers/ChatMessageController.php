<?php

namespace App\Http\Controllers;

use App\Models\Conversation;
use App\Models\Message;
use App\Support\ChatAccess;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;

class ChatMessageController extends Controller
{
    public function typing(Request $request, Conversation $conversation): Response
    {
        $viewer = $request->user();
        ChatAccess::requireMember($conversation, $viewer);
        $other = $conversation->kind === 'direct' ? $conversation->participants()->where('users.id', '!=', $viewer->id)->first() : null;

        if ($conversation->request_status === 'accepted' && $conversation->kind === 'direct'
            && ChatAccess::canPost($conversation, $viewer, $other)) {
            $conversation->participants()->updateExistingPivot($viewer->id, ['typing_until' => now()->addSeconds(8)]);
        }

        return response()->noContent();
    }

    public function edit(Request $request, Message $message): RedirectResponse
    {
        $data = $request->validate(['body' => ['required', 'string', 'max:4000']]);
        DB::transaction(function () use ($request, $message, $data) {
            $chat = Conversation::lockForUpdate()->findOrFail($message->conversation_id);
            $message = Message::lockForUpdate()->findOrFail($message->id);
            $viewer = $request->user();
            ChatAccess::requireMessage($message, $viewer);
            $other = $chat->kind === 'direct' ? $chat->participants()->where('users.id', '!=', $viewer->id)->first() : null;
            abort_unless($message->user_id === $viewer->id && ! $message->removed_at
                && ChatAccess::canPost($chat, $viewer, $other), 403);
            $message->update(['body' => $data['body'], 'edited_at' => now()]);
        });

        return back();
    }

    public function hide(Request $request, Message $message): RedirectResponse
    {
        $viewer = $request->user();
        ChatAccess::requireMessage($message, $viewer);
        DB::table('message_hides')->insertOrIgnore(['message_id' => $message->id, 'user_id' => $viewer->id]);

        return back();
    }

    public function pin(Request $request, Message $message): RedirectResponse
    {
        $data = $request->validate(['pinned' => ['required', 'boolean']]);
        DB::transaction(function () use ($message, $request, $data) {
            $chat = Conversation::lockForUpdate()->findOrFail($message->conversation_id);
            $current = Message::lockForUpdate()->findOrFail($message->id);
            ChatAccess::requireMessage($current, $request->user());
            abort_unless(! $current->removed_at && ChatAccess::canInteract($chat, $request->user()), 403);
            $chat->messages()->when($data['pinned'], fn ($query) => $query
                ->whereNotNull('pinned_at'), fn ($query) => $query->whereKey($current->id))
                ->reorder()->update(['pinned_at' => null]);
            if ($data['pinned']) {
                $current->update(['pinned_at' => now()]);
            }
        });

        return back();
    }

    public function react(Request $request, Message $message): RedirectResponse
    {
        $data = $request->validate(['emoji' => ['present', 'nullable', Rule::in(['👍', '❤️', '😂', '🎨', '🔥', '✨'])]]);
        $viewer = $request->user();

        DB::transaction(function () use ($viewer, $message, $data) {
            $current = Message::lockForUpdate()->findOrFail($message->id);
            ChatAccess::requireMessage($current, $viewer);
            abort_if($current->removed_at || ! ChatAccess::canInteract($current->conversation, $viewer), 403);
            $key = ['message_id' => $current->id, 'user_id' => $viewer->id];
            if ($data['emoji'] === null) {
                DB::table('message_reactions')->where($key)->delete();
            } else {
                DB::table('message_reactions')->upsert([$key + ['emoji' => $data['emoji']]], ['message_id', 'user_id'], ['emoji']);
            }
        });

        return back();
    }

    public function choice(Request $request, Message $message): RedirectResponse
    {
        $data = $request->validate(['index' => ['required', 'integer', 'min:0', 'max:9'], 'selected' => ['required', 'boolean']]);
        $viewer = $request->user();
        DB::transaction(function () use ($message, $viewer, $data) {
            $current = Message::lockForUpdate()->findOrFail($message->id);
            ChatAccess::requireMessage($current, $viewer);
            abort_if(! ChatAccess::canInteract($current->conversation, $viewer) || $current->removed_at || ! isset($current->interactive['options'][$data['index']]), 403);
            $choices = DB::table('message_choices')->where('message_id', $current->id)->where('user_id', $viewer->id);
            if ($current->interactive['type'] === 'checklist') {
                DB::table('message_choices')->where('message_id', $current->id)->where('option_index', $data['index'])->delete();
                if (! $data['selected']) {
                    return;
                }
            } elseif ($data['selected']) {
                (clone $choices)->delete();
            }
            if ($data['selected']) {
                DB::table('message_choices')->insertOrIgnore([
                    'message_id' => $current->id, 'user_id' => $viewer->id, 'option_index' => $data['index'],
                ]);
            } else {
                $choices->where('option_index', $data['index'])->delete();
            }
        });

        return back();
    }

    public function unschedule(Request $request, Conversation $conversation, int $scheduled): RedirectResponse
    {
        ChatAccess::requireMember($conversation, $request->user());
        DB::table('scheduled_messages')->where('conversation_id', $conversation->id)->where('user_id', $request->user()->id)
            ->where('id', $scheduled)->whereIn('status', ['pending', 'failed'])->delete();

        return back();
    }
}
