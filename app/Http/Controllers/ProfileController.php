<?php

namespace App\Http\Controllers;

use App\Http\Resources\PostResource;
use App\Http\Resources\UserResource;
use App\Models\Block;
use App\Models\Post;
use App\Models\StickerPack;
use App\Models\User;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class ProfileController extends Controller
{
    public function show(Request $request, string $username): Response
    {
        $profile = User::where('username', $username)->firstOrFail();
        $viewer = $request->user();
        $tab = in_array($request->query('tab'), ['works', 'albums', 'comics', 'credited'], true) ? $request->query('tab') : 'works';

        $profile->loadCount(['followers', 'following', 'posts']);

        $posts = match ($tab) {
            'credited' => Post::visibleTo($viewer)->where('credited_artist_id', $profile->id)
                ->whereHas('creditedArtist', fn ($query) => $query->whereNull('banned_at')->whereNotNull('email_verified_at')),
            'albums' => $profile->posts()->where('type', 'album'),
            'comics' => $profile->posts()->where('type', 'comic'),
            default => $profile->posts(),
        };

        $posts = $posts
            ->visibleRating($viewer ? (bool) $viewer->show_explicit : false)
            ->latest()
            ->with(['user', 'media.media', 'chapter.comic'])
            ->take(60)
            ->get();

        return Inertia::render('Profile/Show', [
            'profile' => (new UserResource($profile))->resolve(),
            'posts' => PostResource::collection($posts)->resolve(),
            'comics' => $profile->comics()
                ->withCount('chapters')
                ->with(['chapters' => fn ($q) => $q->limit(1)->with(['pages' => fn ($p) => $p->limit(1)->with('media')])])
                ->latest()
                ->get()
                ->map(fn ($c) => [
                    'id' => $c->id,
                    'slug' => $c->slug,
                    'title' => $c->title,
                    'rating' => $c->rating,
                    'chapters' => $c->chapters_count,
                    'cover' => $c->cover()?->thumbUrl(),
                    'url' => route('comics.show', $c->slug, false),
                ]),
            'tab' => $tab,
            'isOwner' => $viewer && $viewer->id === $profile->id,
            'isFollowing' => $viewer ? $viewer->following()->whereKey($profile->id)->exists() : false,
            'hasStickerPacks' => $viewer && StickerPack::where('user_id', $profile->id)->exists(),
            'blockedCount' => $viewer ? Block::where('blocker_id', $viewer->id)->count() : 0,
        ]);
    }
}
