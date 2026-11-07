<?php

namespace App\Http\Controllers;

use App\Http\Resources\PostResource;
use App\Models\Post;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class FollowingController extends Controller
{
    public function index(Request $request): Response
    {
        $user = $request->user();
        $followingIds = \App\Models\Follow::where('follower_id', $user->id)->pluck('following_id');
        $cursor = $request->query('cursor');

        $query = Post::query()
            ->with(['user', 'media.media', 'chapter.comic'])
            ->whereIn('user_id', $followingIds)
            ->visibleRating((bool) $user->show_explicit)
            ->orderByDesc('created_at');

        if ($cursor) {
            $query->where('id', '>', 0);
        }

        $posts = $query->cursorPaginate(15);

        return Inertia::render('Following/Index', [
            'posts' => PostResource::collection($posts->items())->resolve(),
            'nextCursor' => $posts->nextCursor()?->encode(),
            'followingCount' => $followingIds->count(),
        ]);
    }
}
