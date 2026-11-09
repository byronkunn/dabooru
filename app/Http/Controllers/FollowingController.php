<?php

namespace App\Http\Controllers;

use App\Http\Resources\PostResource;
use App\Models\Block;
use App\Models\Follow;
use App\Models\Post;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class FollowingController extends Controller
{
    public function index(Request $request): Response|JsonResponse
    {
        $user = $request->user();
        $followingIds = Follow::where('follower_id', $user->id)
            ->whereNotIn('following_id', Block::counterpartIds($user->id))
            ->pluck('following_id');
        $query = Post::query()
            ->with(['user', 'media.media', 'chapter.comic'])
            ->withExists([
                'likes as viewer_liked' => fn ($query) => $query->where('user_id', $user->id),
                'bookmarks as viewer_saved' => fn ($query) => $query->where('user_id', $user->id),
            ])
            ->where(fn ($query) => $query->whereIn('user_id', $followingIds)
                ->orWhereHas('tags', fn ($tags) => $tags->whereIn('tags.id', $user->followedTags()->select('tags.id'))))
            ->visibleTo($user)
            ->visibleRating((bool) $user->show_explicit)
            ->excludingTags(\App\Support\CollectionLibrary::mutedTags($request))
            ->orderByDesc('created_at')->orderByDesc('id');

        $posts = $query->cursorPaginate(15);
        $feed = [
            'posts' => $posts->getCollection()->map(fn (Post $post) => (new PostResource($post))->resolve() + [
                'liked' => (bool) $post->viewer_liked,
                'saved' => (bool) $post->viewer_saved,
            ])->all(),
            'nextCursor' => $posts->nextCursor()?->encode(),
        ];
        if ($request->wantsJson()) {
            return response()->json($feed);
        }

        return Inertia::render('Following/Index', $feed + [
            'followingCount' => $followingIds->count(),
            'followingUsers' => $user->following()->whereIn('users.id', $followingIds)->orderBy('username')
                ->simplePaginate(20, ['users.id', 'name', 'username'], 'users_page')->withQueryString(),
        ]);
    }
}
