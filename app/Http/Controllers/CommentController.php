<?php

namespace App\Http\Controllers;

use App\Models\Comment;
use App\Models\Post;
use App\Models\AppNotification;
use App\Models\Setting;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

class CommentController extends Controller
{
    public function store(Request $request, Post $post): RedirectResponse
    {
        $data = $request->validate([
            'body' => ['required', 'string', 'min:1', 'max:2000'],
            'parent_id' => ['nullable', 'integer', 'exists:comments,id'],
        ]);

        $comment = Comment::create([
            'post_id' => $post->id,
            'user_id' => $request->user()->id,
            'parent_id' => $data['parent_id'] ?? null,
            'body' => $data['body'],
        ]);

        $post->increment('comments_count');

        if ($post->user_id && $post->user_id !== $request->user()->id && Setting::enabled($post->user_id, 'notification_comments')) {
            AppNotification::create([
                'user_id' => $post->user_id,
                'actor_id' => $request->user()->id,
                'type' => 'comment',
                'subject_type' => Post::class,
                'subject_id' => $post->id,
                'data' => ['post_id' => $post->id, 'comment_id' => $comment->id],
            ]);
        }

        return back();
    }
}
