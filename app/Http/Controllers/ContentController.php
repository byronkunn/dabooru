<?php

namespace App\Http\Controllers;

use App\Models\Comment;
use App\Models\Conversation;
use App\Models\Message;
use App\Models\Post;
use App\Models\Tag;
use App\Support\ChatAccess;
use App\Support\MessageAttachments;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use Inertia\Inertia;
use Inertia\Response;

/**
 * Owners editing and deleting their own worked content.
 *
 * Everything here is soft-deleted or edited by its owner only. Moderators go
 * through the admin panel, which logs to moderation_actions.
 */
class ContentController extends Controller
{
    /* ---------------------------------------------------------------- posts */

    public function edit(Request $request, Post $post): Response
    {
        $this->authorizeOwner($request, $post->user_id);

        return Inertia::render('Posts/Edit', [
            'post' => [
                'id' => $post->id,
                'type' => $post->type,
                'rating' => $post->rating,
                'is_original' => (bool) $post->is_original,
                'artist_name' => $post->artist_name,
                'source_url' => $post->source_url,
                'title' => $post->title,
                'caption' => $post->caption,
                'tags' => $post->tags()->pluck('name')->all(),
                'tag_version' => $post->tag_version,
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
            'tags' => ['sometimes', 'nullable', 'string', 'max:2050'],
            'base_tag_version' => ['sometimes', 'integer', 'min:0'],
            'rating' => ['required', 'in:sfw,suggestive,explicit'],
            'is_original' => ['nullable', 'boolean'],
            'artist_name' => ['nullable', 'string', 'max:120'],
            'source_url' => ['nullable', 'url', 'max:500'],
        ]);

        $tags = array_key_exists('tags', $data) ? Tag::parse($data['tags'], 50) : null;
        $baseTagVersion = $data['base_tag_version'] ?? null;
        unset($data['tags'], $data['base_tag_version']);

        if (isset($data['is_original']) && $data['is_original']) {
            $data['artist_name'] = null;
            $data['source_url'] = null;
        }

        DB::transaction(function () use ($post, $data, $tags, $baseTagVersion) {
            $post = Post::lockForUpdate()->findOrFail($post->id);
            if ($tags !== null && $baseTagVersion !== null && $post->tag_version !== $baseTagVersion) {
                throw ValidationException::withMessages([
                    'base_tag_version' => 'Tags changed since you opened this form. Reload and compare the latest tags before saving.',
                ]);
            }
            $post->update($data);

            if ($tags !== null) {
                Tag::syncFor($post, $tags);
            }
        });

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

        DB::transaction(function () use ($comment) {
            $post = Post::lockForUpdate()->find($comment->post_id);
            Comment::lockForUpdate()->findOrFail($comment->id)->delete();
            if ($post) {
                $post->forceFill(['comments_count' => $post->comments()->count()])->save();
            }
        });

        return back()->with('success', 'Comment deleted.');
    }

    /* ------------------------------------------------------------- messages */

    public function destroyMessage(Request $request, Message $message): RedirectResponse
    {
        DB::transaction(function () use ($request, $message) {
            Conversation::lockForUpdate()->findOrFail($message->conversation_id);
            $current = Message::lockForUpdate()->findOrFail($message->id);
            $this->authorizeOwner($request, $current->user_id);
            ChatAccess::requireMessage($current, $request->user());
            $mediaId = $current->media_id;
            $current->update([
                'body' => '[deleted]', 'media_id' => null, 'shared_post_id' => null, 'interactive' => null,
                'forwarded_from' => null, 'reply_to_id' => null, 'spoiler' => false, 'attachment_name' => null, 'sticker_pack_id' => null, 'pinned_at' => null, 'removed_at' => now(),
            ]);
            Conversation::bumpMessageRevision($current->conversation_id);
            MessageAttachments::purgeAfterCommit($mediaId);
        });

        return back();
    }

    private function authorizeOwner(Request $request, ?int $ownerId): void
    {
        abort_unless($ownerId && $ownerId === $request->user()->id, 403, 'You can only change your own content.');
    }
}
