<?php

namespace App\Http\Controllers;

use App\Models\Conversation;
use App\Models\Setting;
use App\Models\User;
use App\Support\ChatAccess;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;

class ChatRoomController extends Controller
{
    public function store(Request $request): RedirectResponse
    {
        $request->validate(['kind' => ['required', Rule::in(['saved'])]]);
        $viewer = $request->user();
        abort_if($viewer->isBanned(), 403);

        $chat = DB::transaction(function () use ($viewer) {
            User::whereKey($viewer->id)->lockForUpdate()->firstOrFail();
            $existing = Conversation::where('kind', 'saved')
                ->whereHas('participants', fn ($query) => $query->whereKey($viewer->id))->first();

            return $existing ?? tap(Conversation::create(['kind' => 'saved', 'title' => 'Saved Messages']),
                fn (Conversation $saved) => $saved->participants()->attach($viewer->id));
        });

        return redirect()->route('messages.show', $chat);
    }

    public function requestDecision(Request $request, Conversation $conversation): RedirectResponse
    {
        $data = $request->validate(['decision' => ['required', Rule::in(['accepted', 'declined'])]]);
        $viewer = $request->user();

        DB::transaction(function () use ($viewer, $conversation, $data) {
            abort_unless($conversation->kind === 'direct' && $conversation->request_status === 'pending', 403);
            $sender = User::findOrFail($conversation->requested_by);
            if ($data['decision'] === 'accepted') {
                ChatAccess::lockAccounts($viewer, $sender);
                abort_unless(ChatAccess::canContact($viewer, $sender), 403);
            }
            $chat = Conversation::lockForUpdate()->findOrFail($conversation->id);
            ChatAccess::requireMember($chat, $viewer);
            abort_unless($chat->kind === 'direct' && $chat->request_status === 'pending' && (int) $chat->requested_by !== $viewer->id, 403);
            $chat->update(['request_status' => $data['decision']]);
            if ($data['decision'] === 'declined') {
                $chat->participants()->updateExistingPivot($viewer->id, ['archived' => true]);
            }
        });

        return $data['decision'] === 'accepted' ? back() : redirect()->route('messages.index');
    }

    public function privacy(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'chat_dm_permission' => ['required', Rule::in(['anyone', 'following', 'nobody'])],
            'chat_read_receipts' => ['required', 'boolean'],
            'chat_allow_forwarding' => ['sometimes', 'boolean'],
        ]);

        DB::transaction(function () use ($request, $data) {
            User::whereKey($request->user()->id)->lockForUpdate()->firstOrFail();
            foreach ($data as $key => $value) {
                Setting::updateOrCreate(
                    ['user_id' => $request->user()->id, 'key' => $key],
                    ['value' => is_bool($value) ? (string) (int) $value : $value],
                );
            }
        });

        return back()->with('success', 'Messaging privacy saved.');
    }

    public function quickReplies(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'replies' => ['present', 'array', 'list', 'max:12'],
            'replies.*' => ['required', 'string', 'max:400', 'distinct'],
        ]);
        Setting::updateOrCreate(
            ['user_id' => $request->user()->id, 'key' => 'chat_quick_replies'],
            ['value' => json_encode($data['replies'], JSON_THROW_ON_ERROR)],
        );

        return back()->with('success', 'Quick replies saved.');
    }

    public function preferences(Request $request, Conversation $conversation): RedirectResponse
    {
        $data = $request->validate([
            'pinned' => ['sometimes', 'boolean'],
            'archived' => ['sometimes', 'boolean'],
            'muted' => ['sometimes', 'boolean'],
            'marked_unread' => ['sometimes', 'boolean'],
            'folder' => ['sometimes', 'nullable', 'string', 'max:40'],
        ]);
        $viewer = $request->user();
        ChatAccess::requireMember($conversation, $viewer);
        $conversation->participants()->updateExistingPivot($viewer->id, $data);

        return ($data['marked_unread'] ?? false) || ($data['archived'] ?? false)
            ? redirect()->route('messages.index') : back();
    }

    public function settings(Request $request, Conversation $conversation): RedirectResponse
    {
        $data = $request->validate([
            'auto_delete_seconds' => ['required', 'integer', Rule::in([0, 3600, 86400, 604800])],
        ]);

        DB::transaction(function () use ($request, $conversation, $data) {
            $chat = Conversation::lockForUpdate()->findOrFail($conversation->id);
            $viewer = $request->user();
            ChatAccess::requireMember($chat, $viewer);
            abort_unless(ChatAccess::canInteract($chat, $viewer), 403);
            $chat->update($data);
        });

        return back()->with('success', 'Chat settings saved.');
    }
}
