<?php

namespace App\Http\Controllers;

use App\Models\Block;
use App\Models\Conversation;
use App\Models\Follow;
use App\Models\User;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class BlockController extends Controller
{
    public function index(Request $request): Response
    {
        $blocks = Block::with('blocked')
            ->where('blocker_id', $request->user()->id)
            ->latest()
            ->get();

        return Inertia::render('Settings/Blocked', [
            'blocks' => $blocks->map(fn (Block $b) => [
                'id' => $b->id,
                'user' => $b->blocked ? [
                    'name' => $b->blocked->name,
                    'username' => $b->blocked->username,
                    'avatar' => $b->blocked->avatarUrl(),
                ] : null,
                'when' => $b->created_at?->diffForHumans(short: true),
            ])->filter(fn ($b) => $b['user'])->values()->all(),
        ]);
    }

    public function store(Request $request, User $user): RedirectResponse
    {
        abort_if($user->id === $request->user()->id, 422, 'You cannot block yourself.');

        Block::firstOrCreate([
            'blocker_id' => $request->user()->id,
            'blocked_id' => $user->id,
        ]);

        // blocking ends the follow either way and closes any DM thread
        Follow::where('follower_id', $request->user()->id)->where('following_id', $user->id)->delete();
        Follow::where('follower_id', $user->id)->where('following_id', $request->user()->id)->delete();

        $conversation = Conversation::between($request->user()->id, $user->id);
        if ($conversation->messages()->count() === 0) {
            $conversation->delete();
        }

        return back()->with('success', "Blocked @{$user->username}.");
    }

    public function destroy(Request $request, User $user): RedirectResponse
    {
        Block::where('blocker_id', $request->user()->id)
            ->where('blocked_id', $user->id)
            ->delete();

        return back()->with('success', "Unblocked @{$user->username}.");
    }
}
