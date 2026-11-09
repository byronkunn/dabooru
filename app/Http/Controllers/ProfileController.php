<?php

namespace App\Http\Controllers;

use App\Http\Resources\PostResource;
use App\Http\Resources\UserResource;
use App\Models\Block;
use App\Models\Conversation;
use App\Models\Post;
use App\Models\SavedCollection;
use App\Models\StickerPack;
use App\Models\User;
use App\Support\ChatAccess;
use App\Support\CollectionLibrary;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class ProfileController extends Controller
{
    public function connections(Request $request, string $username, string $type): JsonResponse
    {
        $profile = User::where('username', $username)->firstOrFail();
        $people = $profile->{$type}()
            ->verified()
            ->orderBy('users.username')
            ->paginate(25);

        return response()->json([
            'data' => $people->getCollection()->map(fn (User $user) => [
                'id' => $user->id,
                'name' => $user->name,
                'username' => $user->username,
                'avatar' => $user->avatarUrl(),
                'url' => route('profile.show', $user->username, false),
            ]),
            'has_more' => $people->hasMorePages(),
        ]);
    }

    public function show(Request $request, string $username): Response
    {
        $profile = User::where('username', $username)->firstOrFail();
        $viewer = $request->user();
        $blocked = $viewer && Block::where('blocker_id', $viewer->id)->where('blocked_id', $profile->id)->exists();
        $blockedBy = $viewer && Block::where('blocker_id', $profile->id)->where('blocked_id', $viewer->id)->exists();
        $conversation = $viewer && $viewer->id !== $profile->id
            ? Conversation::existingBetween($viewer->id, $profile->id) : null;
        $tab = in_array($request->query('tab'), ['works', 'albums', 'comics', 'credited', 'collections'], true) ? $request->query('tab') : 'works';

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
        $collections = $tab === 'collections' && ! $blocked && ! $blockedBy
            ? SavedCollection::where('user_id', $profile->id)->where('is_public', true)->orderBy('name')->get()
                ->map(fn ($collection) => CollectionLibrary::summary($profile->id, $viewer, $collection, CollectionLibrary::mutedTags($request)) + [
                    'url' => route('collections.public', [$profile->username, $collection], false),
                ])->all()
            : [];

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
                    'cover' => $c->cover()?->thumbUrl() ?: $c->cover()?->url(),
                    'url' => route('comics.show', $c->slug, false),
                ]),
            'collections' => $collections,
            'tab' => $tab,
            'isOwner' => $viewer && $viewer->id === $profile->id,
            'isFollowing' => $viewer ? $viewer->following()->whereKey($profile->id)->exists() : false,
            'relationship' => [
                'blocked' => $blocked,
                'blockedBy' => $blockedBy,
                'followsYou' => $viewer ? $profile->following()->whereKey($viewer->id)->exists() : false,
                'canFollow' => $viewer && $viewer->id !== $profile->id && ! $viewer->isBanned()
                    && ! $profile->isBanned() && ! $blocked && ! $blockedBy,
                'canMessage' => $viewer && $viewer->id !== $profile->id && ChatAccess::canContact($viewer, $profile)
                    && (($conversation && $conversation->request_status !== 'draft') || ChatAccess::permitsContact($profile, $viewer)),
            ],
            'hasStickerPacks' => $viewer && StickerPack::where('user_id', $profile->id)->exists(),
            'blockedCount' => $viewer ? Block::where('blocker_id', $viewer->id)->count() : 0,
        ]);
    }
}
