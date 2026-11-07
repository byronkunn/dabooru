<?php

namespace App\Http\Controllers;

use App\Http\Resources\PostResource;
use App\Http\Resources\UserResource;
use App\Models\ArtistCreditClaim;
use App\Models\Post;
use App\Models\PostView;
use App\Models\SavedCollection;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class PostController extends Controller
{
    public function show(Request $request, Post $post): Response
    {
        $post->load(['user', 'media.media', 'chapter.comic', 'tags', 'creditedArtist:id,username,name,banned_at,email_verified_at']);
        $this->recordView($request, $post);

        $viewer = $request->user();
        $more = Post::query()
            ->with(['user', 'media.media', 'chapter.comic'])
            ->where('user_id', $post->user_id)
            ->whereKeyNot($post->id)
            ->latest()
            ->take(8)
            ->get();

        return Inertia::render('Posts/Show', [
            'post' => (new PostResource($post))->resolve(),
            'creditClaim' => $viewer && $viewer->email_verified_at && $post->isExternalArtist() && ! $post->credited_artist_id
                ? ['status' => ArtistCreditClaim::where('user_id', $viewer->id)->where('post_id', $post->id)->value('status')]
                : null,
            'comments' => $post->comments()
                ->with('user')
                ->whereNull('parent_id')
                ->latest()
                ->take(50)
                ->get()
                ->map(fn ($c) => [
                    'id' => $c->id,
                    'body' => $c->body,
                    'created_at_human' => $c->created_at->diffForHumans(short: true),
                    'user' => (new UserResource($c->user))->resolve(),
                ]),
            'more' => PostResource::collection($more)->resolve(),
            'liked' => $viewer ? $post->likes()->where('user_id', $viewer->id)->exists() : false,
            'collections' => $viewer
                ? SavedCollection::where('user_id', $viewer->id)->orderBy('name')->get(['id', 'name'])
                : [],
            'saved' => $viewer ? $post->bookmarkedBy()->where('users.id', $viewer->id)->exists() : false,
            'followedAuthor' => $viewer ? $viewer->following()->whereKey($post->user_id)->exists() : false,
        ]);
    }

    private function recordView(Request $request, Post $post): void
    {
        $key = $request->user()
            ? 'u'.$request->user()->id
            : 'a'.substr(hash_hmac('sha256', $request->ip().'|'.now()->toDateString(), config('app.key')), 0, 32);

        // insertOrIgnore makes the unique (post, viewer, day) index the lock,
        // so two concurrent requests can't both count the same view.
        $inserted = PostView::insertOrIgnore([
            'post_id' => $post->id,
            'viewer_key' => $key,
            'viewed_on' => now()->toDateString(),
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        if ($inserted) {
            $post->increment('views');
        }
    }
}
