<?php

namespace App\Http\Controllers;

use App\Http\Resources\PostResource;
use App\Models\Block;
use App\Models\Comic;
use App\Models\CuratedShelf;
use App\Models\Post;
use App\Models\User;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class DiscoverController extends Controller
{
    /**
     * "Show less like this": mute the tags of a suggestion so similar works stop
     * appearing. Kept here rather than as a separate mute screen because the
     * reader's intent is about what they are looking at right now.
     */
    public function showLess(Request $request): \Illuminate\Http\RedirectResponse
    {
        $data = $request->validate(['post' => ['required', 'integer', 'exists:posts,id']]);
        $viewer = $request->user();
        $post = Post::with('tags')->findOrFail($data['post']);

        // the tags that actually make this work distinctive
        $tags = $post->tags->pluck('name')->take(3);

        foreach ($tags as $tag) {
            \App\Support\CollectionLibrary::addMute($viewer, $tag);
        }

        return back()->with('success', $tags->isEmpty()
            ? 'Noted. We will show less of this.'
            : 'Showing less of: '.$tags->map(fn ($t) => '#'.$t)->join(', ').'.');
    }

    public function index(Request $request): Response
    {
        $viewer = $request->user();
        $now = now();
        $day = $now->copy()->subDay();
        $twoDays = $now->copy()->subDays(2);
        $week = $now->copy()->subWeek();
        $visible = Post::visibleTo($viewer)->visibleRating((bool) $viewer?->show_explicit)
            // muted tags stay out of every Discover rail, including suggestions built from them
            ->excludingTags($viewer ? \App\Support\CollectionLibrary::mutedFor($viewer) : []);
        $liked = $viewer ? (clone $visible)->with('tags')->whereHas('likes', fn ($query) => $query
            ->where('user_id', $viewer->id)->where('created_at', '>=', $now->copy()->subDays(90)))
            ->orderByDesc('id')->limit(100)->get() : collect();
        $likedTags = $liked->flatMap(fn ($post) => $post->tags->pluck('id'))->unique()->values();
        $likedAuthors = $liked->filter(fn ($post) => ! $post->isExternalArtist())->pluck('user_id')->filter()->unique()->values();
        $likedArtists = $liked->pluck('artist_name')->filter()->unique()->values();
        $followingIds = $viewer ? $viewer->following()->pluck('users.id') : collect();
        $followedTags = $viewer ? $viewer->followedTags()->pluck('tags.id') : collect();

        $signals = (clone $visible)
            ->when($viewer, fn ($query) => $query
                ->where(fn ($authors) => $authors->whereNull('user_id')->orWhere('user_id', '!=', $viewer->id))
                ->whereDoesntHave('likes', fn ($likes) => $likes->where('user_id', $viewer->id)))
            ->withCount([
                'likes as recent_likes' => fn ($query) => $query->where('created_at', '>=', $day)->where('created_at', '<=', $now),
                'likes as previous_likes' => fn ($query) => $query->where('created_at', '>=', $twoDays)->where('created_at', '<', $day),
                'likes as week_likes' => fn ($query) => $query->where('created_at', '>=', $week)->where('created_at', '<=', $now),
                'tags as followed_tag_matches' => fn ($query) => $query->whereIn('tags.id', $followedTags),
                'tags as liked_tag_matches' => fn ($query) => $query->whereIn('tags.id', $likedTags),
                'user as followed_author' => fn ($query) => $query->whereIn('users.id', $followingIds),
                'user as liked_author' => fn ($query) => $query->whereIn('users.id', $likedAuthors)
                    ->where('posts.is_original', true)
                    ->where(fn ($credit) => $credit->whereNull('posts.artist_name')->orWhere('posts.artist_name', '')),
            ])
            ->selectRaw('CASE WHEN artist_name IN ('.($likedArtists->isEmpty() ? 'NULL' : $likedArtists->map(fn () => '?')->implode(',')).') THEN 1 ELSE 0 END as liked_artist', $likedArtists->all());

        // Bound each signal so one viral post or heavily tagged work cannot dominate indefinitely.
        $posts = Post::query()->fromSub($signals->toBase(), 'posts')
            ->with(['user', 'media.media', 'chapter.comic', 'tags', 'creditedArtist'])
            ->orderByRaw('(
                followed_author * 6
                + (CASE WHEN followed_tag_matches > 3 THEN 3 ELSE followed_tag_matches END) * 4
                + (CASE WHEN liked_tag_matches > 3 THEN 3 ELSE liked_tag_matches END) * 3
                + liked_author * 4 + liked_artist * 4
                + (CASE WHEN recent_likes > 20 THEN 20 ELSE recent_likes END) * 0.4
                + (CASE WHEN week_likes > 50 THEN 50 ELSE week_likes END) * 0.08
                + (CASE WHEN recent_likes <= previous_likes THEN 0
                    WHEN recent_likes - previous_likes > 20 THEN 20 ELSE recent_likes - previous_likes END) * 0.5
                + (CASE WHEN created_at >= ? THEN 2 ELSE 0 END)
            ) DESC', [$week])
            ->orderByDesc('created_at')->orderByDesc('id')
            ->simplePaginate(24)->withQueryString();

        return Inertia::render('Discovery/Discover', [
            'posts' => $posts->getCollection()->map(function (Post $post) {
                $reasons = [];
                if ($post->followed_author) {
                    $reasons[] = 'From a user you follow';
                }
                if ($post->followed_tag_matches) {
                    $reasons[] = 'Matches tags you follow';
                }
                if ($post->liked_tag_matches || $post->liked_author || $post->liked_artist) {
                    $reasons[] = 'Similar to work you liked';
                }
                if ($post->recent_likes > $post->previous_likes && $post->recent_likes >= 2) {
                    $reasons[] = 'Gaining popularity';
                }
                if ($post->week_likes >= 3) {
                    $reasons[] = 'Popular this week';
                }

                return (new PostResource($post))->resolve() + [
                    'recommendation_reasons' => $reasons ?: ['Something new to explore'],
                ];
            }),
            'staffPicks' => $posts->onFirstPage() ? $this->staffPicks($viewer) : [],
            'personalized' => $liked->isNotEmpty() || $followingIds->isNotEmpty() || $followedTags->isNotEmpty(),
            'nextPage' => $posts->nextPageUrl(),
            'previousPage' => $posts->previousPageUrl(),
        ]);
    }

    private function staffPicks(?User $viewer): array
    {
        $shelves = CuratedShelf::where('published', true)->latest()->orderByDesc('id')->limit(4)->get();
        if ($shelves->isEmpty()) {
            return [];
        }

        $entries = $shelves->flatMap(fn (CuratedShelf $shelf) => $shelf->entries)->groupBy('kind');
        $ids = fn (string $kind) => $entries->get($kind, collect())->pluck('id')->unique()->all();
        $showExplicit = (bool) $viewer?->show_explicit;
        $works = Post::visibleTo($viewer)->visibleRating($showExplicit)->whereKey($ids('work'))->with('media.media')->get()->keyBy('id');
        $comics = Comic::visibleRating($showExplicit)->whereKey($ids('comic'))->whereHas('chapters')
            ->when($viewer, fn ($query) => $query->whereNotIn('user_id', Block::counterpartIds($viewer->id)))
            ->get()->keyBy('id');
        $artists = User::whereKey($ids('artist'))->whereNull('banned_at')->where('is_unsorted', false)
            ->when($viewer, fn ($query) => $query->whereNotIn('id', Block::counterpartIds($viewer->id)))
            ->get()->keyBy('id');

        return $shelves->map(function (CuratedShelf $shelf) use ($works, $comics, $artists) {
            $items = collect($shelf->entries)->map(function (array $entry) use ($works, $comics, $artists) {
                $item = match ($entry['kind']) {
                    'work' => $works->get($entry['id']),
                    'comic' => $comics->get($entry['id']),
                    'artist' => $artists->get($entry['id']),
                    default => null,
                };
                if (! $item) {
                    return null;
                }

                return match ($entry['kind']) {
                    'work' => ['kind' => 'Work', 'label' => $item->title ?: 'Untitled', 'url' => route('posts.show', $item, false),
                        'image' => $item->rating === 'explicit' ? null : ($item->cover()?->thumbUrl() ?: $item->cover()?->url())],
                    'comic' => ['kind' => 'Comic', 'label' => $item->title, 'url' => route('comics.show', $item->slug, false), 'image' => null],
                    'artist' => ['kind' => 'Artist', 'label' => $item->name, 'url' => route('profile.show', $item, false), 'image' => $item->avatarUrl()],
                };
            })->filter()->values();

            return $shelf->only(['id', 'title', 'description']) + ['items' => $items];
        })->filter(fn (array $shelf) => $shelf['items']->isNotEmpty())->values()->all();
    }
}
