<?php

namespace App\Http\Controllers;

use App\Models\AppNotification;
use App\Models\Block;
use App\Models\Comment;
use App\Models\Post;
use App\Models\Setting;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class CommentController extends Controller
{
    public function store(Request $request, Post $post): RedirectResponse
    {
        abort_unless($this->canComment($request, $post), 403);
        if (is_string($request->input('body'))) {
            $request->merge(['body' => trim($request->input('body'))]);
        }
        $data = $request->validate([
            'body' => ['required', 'string', 'min:1', 'max:2000'],
            'parent_id' => ['nullable', 'integer', 'min:1'],
        ]);

        DB::transaction(function () use ($request, $post, $data) {
            $post = Post::lockForUpdate()->findOrFail($post->id);
            $parent = null;
            if (! empty($data['parent_id'])) {
                $parent = Comment::where('post_id', $post->id)->lockForUpdate()->find($data['parent_id']);
                if (! $parent || $parent->parent_id !== null) {
                    throw ValidationException::withMessages(['parent_id' => 'This comment is no longer available for a reply. Reload the discussion.']);
                }
                if ($parent->user_id && Block::existsBetween($request->user()->id, $parent->user_id)) {
                    abort(403);
                }
            }
            $comment = Comment::create([
                'post_id' => $post->id,
                'user_id' => $request->user()->id,
                'parent_id' => $data['parent_id'] ?? null,
                'body' => $data['body'],
            ]);

            $post->increment('comments_count');

            $recipients = array_unique(array_filter([$post->user_id, $parent?->user_id]));
            foreach ($recipients as $recipient) {
                if ($recipient === $request->user()->id || ! Setting::enabled($recipient, 'notification_comments')) {
                    continue;
                }
                if (Block::existsBetween($request->user()->id, $recipient)) {
                    continue;
                }
                AppNotification::create([
                    'user_id' => $recipient,
                    'actor_id' => $request->user()->id,
                    'type' => 'comment',
                    'subject_type' => Post::class,
                    'subject_id' => $post->id,
                    'data' => ['post_id' => $post->id, 'comment_id' => $comment->id, 'reply' => $parent?->user_id === $recipient],
                ]);
            }
        });

        return empty($data['parent_id'])
            ? redirect()->to(route('posts.show', $post).'#comments')->with('success', 'Comment posted.')
            : back()->with('success', 'Reply posted.');
    }

    public function update(Request $request, Comment $comment): RedirectResponse
    {
        abort_unless($comment->user_id === $request->user()->id, 403);
        $post = $comment->post;
        abort_unless($post && $this->canComment($request, $post), 403);
        if (is_string($request->input('body'))) {
            $request->merge(['body' => trim($request->input('body'))]);
        }
        $data = $request->validate(['body' => ['required', 'string', 'min:1', 'max:2000']]);
        DB::transaction(function () use ($comment, $post, $data) {
            Post::lockForUpdate()->findOrFail($post->id);
            Comment::lockForUpdate()->findOrFail($comment->id)->update($data);
        });

        return back()->with('success', 'Comment updated.');
    }

    public static function canComment(Request $request, Post $post): bool
    {
        $user = $request->user();

        return $user && ! $user->isBanned()
            && (! $post->user_id || ! Block::existsBetween($user->id, $post->user_id));
    }
}
