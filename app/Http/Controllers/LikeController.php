<?php

namespace App\Http\Controllers;

use App\Models\Like;
use App\Models\Post;
use App\Models\AppNotification;
use App\Models\Setting;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

class LikeController extends Controller
{
    public function store(Request $request, Post $post): RedirectResponse
    {
        $like = Like::firstOrCreate([
            'user_id' => $request->user()->id,
            'post_id' => $post->id,
        ]);

        if ($like->wasRecentlyCreated) {
            $post->increment('likes_count');
            if ($post->user_id && $post->user_id !== $request->user()->id && Setting::enabled($post->user_id, 'notification_likes')) {
                AppNotification::create([
                    'user_id' => $post->user_id,
                    'actor_id' => $request->user()->id,
                    'type' => 'like',
                    'subject_type' => Post::class,
                    'subject_id' => $post->id,
                    'data' => ['post_id' => $post->id],
                ]);
            }
        }

        return back();
    }

    public function destroy(Request $request, Post $post): RedirectResponse
    {
        $deleted = Like::where('user_id', $request->user()->id)
            ->where('post_id', $post->id)
            ->delete();

        if ($deleted) {
            $post->decrement('likes_count');
        }

        return back();
    }
}
