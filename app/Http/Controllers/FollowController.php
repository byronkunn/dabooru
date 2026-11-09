<?php

namespace App\Http\Controllers;

use App\Models\Follow;
use App\Models\User;
use App\Models\AppNotification;
use App\Models\Setting;
use App\Models\Block;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class FollowController extends Controller
{
    public function store(Request $request, User $user): RedirectResponse
    {
        abort_if($request->user()->id === $user->id, 422, 'You cannot follow yourself.');

        DB::transaction(function () use ($request, $user) {
            $actor = $request->user();
            $accounts = User::whereKey([$actor->id, $user->id])->orderBy('id')->lockForUpdate()->get()->keyBy('id');
            if ($accounts->get($actor->id)->isBanned() || $accounts->get($user->id)->isBanned()
                || Block::existsBetween($actor->id, $user->id)) {
                throw ValidationException::withMessages(['follow' => 'You cannot follow this account.']);
            }
            $follow = Follow::firstOrCreate([
                'follower_id' => $actor->id,
                'following_id' => $user->id,
            ]);

            if ($follow->wasRecentlyCreated && Setting::enabled($user->id, 'notification_follows')) {
                AppNotification::create([
                    'user_id' => $user->id,
                    'actor_id' => $actor->id,
                    'type' => 'follow',
                    'subject_type' => User::class,
                    'subject_id' => $user->id,
                    'data' => ['user_id' => $user->id],
                ]);
            }
        });

        return back();
    }

    public function destroy(Request $request, User $user): RedirectResponse
    {
        DB::transaction(function () use ($request, $user) {
            Follow::where('follower_id', $request->user()->id)->where('following_id', $user->id)
                ->lockForUpdate()->get()->each->delete();
        });

        return back();
    }
}
