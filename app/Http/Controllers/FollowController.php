<?php

namespace App\Http\Controllers;

use App\Models\Follow;
use App\Models\User;
use App\Models\AppNotification;
use App\Models\Setting;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

class FollowController extends Controller
{
    public function store(Request $request, User $user): RedirectResponse
    {
        abort_if($request->user()->id === $user->id, 422, 'You cannot follow yourself.');

        $follow = Follow::firstOrCreate([
            'follower_id' => $request->user()->id,
            'following_id' => $user->id,
        ]);

        if ($follow->wasRecentlyCreated && Setting::enabled($user->id, 'notification_follows')) {
            AppNotification::create([
                'user_id' => $user->id,
                'actor_id' => $request->user()->id,
                'type' => 'follow',
                'subject_type' => User::class,
                'subject_id' => $user->id,
                'data' => ['user_id' => $user->id],
            ]);
        }

        return back();
    }

    public function destroy(Request $request, User $user): RedirectResponse
    {
        Follow::where('follower_id', $request->user()->id)
            ->where('following_id', $user->id)
            ->delete();

        return back();
    }
}
