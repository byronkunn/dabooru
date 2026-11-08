<?php

namespace App\Http\Controllers;

use App\Models\Block;
use App\Models\Post;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class PostRatingController extends Controller
{
    public function update(Request $request, Post $post): RedirectResponse
    {
        $data = $request->validate(['stars' => ['required', 'integer', 'between:1,5']]);
        $viewer = $request->user();
        abort_if($viewer->isBanned(), 403, 'Restricted accounts cannot rate works.');
        abort_if($post->user_id && Block::existsBetween($viewer->id, $post->user_id), 403, 'You cannot rate this work.');

        DB::transaction(function () use ($post, $viewer, $data) {
            $locked = Post::whereKey($post->id)->lockForUpdate()->firstOrFail();
            $locked->starRatings()->updateOrCreate(
                ['user_id' => $viewer->id],
                ['stars' => $data['stars']],
            );
        });

        return back();
    }
}
