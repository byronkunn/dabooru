<?php

namespace App\Http\Controllers;

use App\Models\Comment;
use App\Models\Message;
use App\Models\Post;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

/**
 * Owners editing and deleting their own worked content.
 *
 * Everything here is soft-deleted or edited by its owner only. Moderators go
 * through the admin panel, which logs to moderation_actions.
 */
class ContentController extends Controller
{
    /* ---------------------------------------------------------------- posts */

    public function edit(Request $request, Post $post): \Inertia\Response
    {
        $this->authorizeOwner($request, $post->user_id);

        return \Inertia\Inertia::render('Posts/Edit', [
            'post' => [
                'id' => $post->id,
                'type' => $post->type,
                'rating' => $post->rating,
                'title' => $post->title,
                'caption' => $post->caption,
                'media' => $post->media()->with('media')->get()->map(fn ($pm) => [
                    'id' => $pm->media?->id,
                    'url' => $pm->media?->url(),
                    'thumb' => $pm->media?->thumbUrl(),
                    'kind' => $pm->media?->kind,
                ])->filter()->values()->all(),
            ],
        ]);
    }

    public function update(Request $request, Post $post): RedirectResponse
    {
        $this->authorizeOwner($request, $post->user_id);

        $data = $request->validate([
            'title' => ['nullable', 'string', 'max:160'],
            'caption' => ['nullable', 'string', 'max:1000'],
            'rating' => ['required', 'in:sfw,suggestive,explicit'],
        ]);

        $post->update($data);

        return redirect()->route('posts.show', $post)->with('success', 'Work updated.');
    }

    public function destroy(Request $request, Post $post): RedirectResponse
    {
        $this->authorizeOwner($request, $post->user_id);

        $post->delete();

        return redirect()
            ->route('profile.show', $request->user()->username)
            ->with('success', 'Work deleted.');
    }

    /* ------------------------------------------------------------- comments */

    public function destroyComment(Request $request, Comment $comment): RedirectResponse
    {
        $this->authorizeOwner($request, $comment->user_id);

        $post = $comment->post;
        $comment->delete();

        if ($post) {
            $post->forceFill(['comments_count' => $post->comments()->count()])->save();
        }

        return back()->with('success', 'Comment deleted.');
    }

    /* ------------------------------------------------------------- messages */

    public function destroyMessage(Request $request, Message $message): RedirectResponse
    {
        $this->authorizeOwner($request, $message->user_id);

        // Messages have no soft-delete column; blank the body so the thread
        // keeps its shape instead of showing a hole.
        $message->forceFill(['body' => '[deleted]', 'media_id' => null])->save();

        return back();
    }

    private function authorizeOwner(Request $request, ?int $ownerId): void
    {
        abort_unless($ownerId && $ownerId === $request->user()->id, 403, 'You can only change your own content.');
    }
}
