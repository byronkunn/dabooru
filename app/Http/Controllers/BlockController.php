<?php

namespace App\Http\Controllers;

use App\Models\Block;
use App\Models\Conversation;
use App\Models\Follow;
use App\Models\User;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
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

        DB::transaction(function () use ($request, $user) {
            $actor = $request->user();
            // Lock the same accounts as the send endpoint so a block and a send are ordered.
            User::whereKey([$actor->id, $user->id])->orderBy('id')->lockForUpdate()->get();

            Block::firstOrCreate(['blocker_id' => $actor->id, 'blocked_id' => $user->id]);

            Follow::where('follower_id', $actor->id)->where('following_id', $user->id)->get()->each->delete();
            Follow::where('follower_id', $user->id)->where('following_id', $actor->id)->get()->each->delete();

            $conversation = Conversation::existingBetween($actor->id, $user->id);
            if ($conversation && ! $conversation->messages()->exists()) {
                $conversation->delete();
            } elseif ($conversation) {
                $conversation->participants()->updateExistingPivot($actor->id, ['typing_until' => null]);
                $conversation->participants()->updateExistingPivot($user->id, ['typing_until' => null]);
            }
        });

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
