<?php

namespace App\Http\Controllers;

use App\Models\Conversation;
use App\Models\Message;
use App\Models\User;
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

        $conversation->load(['participants', 'messages.user']);

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
                ]),
            ],
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'conversation_id' => ['nullable', 'integer', 'exists:conversations,id'],
            'to' => ['nullable', 'string', 'exists:users,username'],
            'body' => ['required', 'string', 'min:1', 'max:4000'],
        ]);

        $conversation = $data['conversation_id']
            ? Conversation::findOrFail($data['conversation_id'])
            : Conversation::between($request->user()->id, User::where('username', $data['to'])->value('id'));

        $this->authorizeParticipant($request, $conversation);

        $conversation->messages()->create([
            'user_id' => $request->user()->id,
            'body' => $data['body'],
            'rating' => 'sfw',
        ]);

        $conversation->forceFill(['last_message_at' => now()])->save();

        return redirect()->route('messages.show', $conversation);
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
