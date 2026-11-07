<?php

namespace App\Http\Controllers;

use App\Models\Like;
use App\Models\Post;
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
