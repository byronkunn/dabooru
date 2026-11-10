<?php

namespace App\Http\Middleware;

use App\Http\Resources\PostResource;
use App\Http\Resources\UserResource;
use App\Models\Announcement;
use App\Models\AppNotification;
use App\Models\Block;
use App\Models\Post;
use App\Models\User;
use App\Support\AgeGate;
use App\Support\ChatAccess;
use App\Support\DemoAccounts;
use App\Support\StaffAccess;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;
use Inertia\Middleware;

class HandleInertiaRequests extends Middleware
{
    protected $rootView = 'app';

    public function version(Request $request): ?string
    {
        return parent::version($request);
    }

    /**
     * Props available to every page.
     */
    public function share(Request $request): array
    {
        $user = $request->user();
        $staffPermissions = $user ? StaffAccess::permissions($user) : [];

        return array_merge(parent::share($request), [
            'ageConfirmed' => AgeGate::confirmed(),
            // what a shared link should look like on another platform
            'share' => fn () => $this->shareFor($request),
            'announcement' => fn () => Announcement::where('published', true)->latest('updated_at')->orderByDesc('id')
                ->first(['id', 'title', 'body', 'updated_at']),
            'sponsorAd' => [
                'image' => config('ads.image'),
                'url' => config('ads.url'),
                'label' => config('ads.label'),
            ],
            'discoveryRail' => fn () => $this->discoveryRail($request, $user),
            // local development only: the one-click demo switcher
            'demoAccounts' => Route::has('demo.login')
                ? DemoAccounts::users()
                : [],
            'auth' => [
                'followed_tags' => fn () => $user ? $user->followedTags()->orderBy('name')->get(['tags.id', 'name']) : [],
                'user' => $user ? [
                    'id' => $user->id,
                    'name' => $user->name,
                    'username' => $user->username,
                    'avatar' => $user->avatarUrl(),
                    'banner' => $user->bannerUrl(),
                    'is_admin' => $user->isAdmin(),
                    'staff_permissions' => $staffPermissions,
                    'show_explicit' => $user->show_explicit,
                    'email_verified' => (bool) $user->email_verified_at,
                ] : null,
                'unread_notifications' => $user ? AppNotification::where('user_id', $user->id)
                    ->where('type', '!=', 'chat_mention')
                    ->whereNull('read_at')
                    ->when(! in_array('moderator', $staffPermissions, true), fn ($query) => $query->where('type', '!=', 'urgent_report'))
                    ->count() : 0,
                'unread_messages' => fn () => $user ? ChatAccess::unreadCount($user) : 0,
            ],
            'flash' => [
                'bookmarkUndo' => fn () => $request->session()->pull('bookmarkUndo'),
                'success' => fn () => $request->session()->pull('success'),
                'error' => fn () => $request->session()->pull('error'),
                'bulkPreview' => fn () => $request->session()->pull('bulkPreview'),
            ],
        ]);
    }

    /**
     * Open Graph data for the current page.
     *
     * A work page previews its own title and thumbnail; everything else falls
     * back to the site defaults in the Blade layout. Explicit work never gets a
     * preview image, so a link posted elsewhere cannot leak the artwork.
     */
    private function shareFor(Request $request): array
    {
        $route = $request->route()?->getName();
        $post = $request->route()?->parameter('post');
        $indexableRoutes = [
            'gallery', 'discover', 'artists.index', 'artists.show', 'profile.show',
            'comics.index', 'comics.show', 'comics.read', 'knowledge.index',
            'knowledge.show', 'legal.terms', 'legal.privacy', 'legal.policy', 'dmca',
        ];
        $robots = in_array($route, $indexableRoutes, true) ? 'index, follow' : 'noindex, nofollow';
        $descriptions = [
            'gallery' => 'Browse artwork, albums, GIFs and video shared by independent artists on BooruHub.',
            'discover' => 'Discover artists and community favorites from the BooruHub art community.',
            'artists.index' => 'Explore the BooruHub artist directory and find creators to follow.',
            'comics.index' => 'Read independent comics and illustrated stories published by artists on BooruHub.',
            'knowledge.index' => 'Browse community maintained tags and art references on BooruHub.',
            'legal.terms' => 'Read the terms that govern use of BooruHub.',
            'legal.privacy' => 'Learn how BooruHub handles account and usage information.',
            'legal.policy' => 'Read BooruHub’s community rules and content policy.',
            'dmca' => 'Review copyright and DMCA information or submit a notice to BooruHub.',
        ];
        $seoTitles = [
            'gallery' => config('app.name').' — Art, Comics & Artist Community',
            'discover' => 'Discover Art & Artists | '.config('app.name'),
            'artists.index' => 'Artist Directory | '.config('app.name'),
            'comics.index' => 'Independent Comics | '.config('app.name'),
            'knowledge.index' => 'Community Tags & Art Wiki | '.config('app.name'),
        ];

        if ($route === 'posts.show' && $post instanceof \App\Models\Post) {
            $media = $post->media->first()?->media;
            $explicit = $post->rating === 'explicit';
            $title = $post->title ?: 'A work on '.config('app.name');

            return [
                'type' => 'article',
                'title' => $title,
                'seo_title' => $title.' | '.config('app.name'),
                'description' => $explicit
                    ? 'Explicit work on '.config('app.name').'. Open the link to view it.'
                    : \Illuminate\Support\Str::limit((string) ($post->caption ?: $title), 160),
                'url' => route('posts.show', $post),
                'image' => ! $explicit && $media ? $media->url() : null,
                'rating' => $explicit ? 'adult' : 'safe',
                'robots' => ! $explicit && ! $post->is_draft && ! $post->moderation_hidden_at
                    ? 'index, follow' : 'noindex, nofollow',
            ];
        }

        if ($route === 'profile.show' && is_string($request->route()?->parameter('username'))) {
            $profile = \App\Models\User::where('username', $request->route()->parameter('username'))->first();

            if ($profile) {
                return [
                    'type' => 'profile',
                    'title' => $profile->name.' (@'.$profile->username.')',
                    'seo_title' => $profile->name.' (@'.$profile->username.') | '.config('app.name'),
                    'description' => \Illuminate\Support\Str::limit((string) ($profile->bio ?: 'Artwork on '.config('app.name')), 160),
                    'url' => route('profile.show', $profile->username),
                    'image' => $profile->avatarUrl(),
                    'robots' => $profile->isBanned() ? 'noindex, nofollow' : $robots,
                ];
            }
        }

        if ($route === 'comics.show' || $route === 'comics.read') {
            // this route binds a slug, not a model
            $slug = request()->route()->parameter('comic');
            $comic = is_string($slug) ? \App\Models\Comic::where('slug', $slug)->first() : $slug;
            if ($comic instanceof \App\Models\Comic) {
                $chapterNumber = $request->route()->parameter('number');
                $chapter = $chapterNumber === null ? null : $comic->chapters()
                    ->where('number', $chapterNumber)->first();
                $chapterExplicit = $chapter?->rating === 'explicit';

                return [
                    'type' => 'book',
                    'title' => $comic->title,
                    'seo_title' => $chapter
                        ? $comic->title.' — Chapter '.$chapter->number.' | '.config('app.name')
                        : $comic->title.' | '.config('app.name'),
                    'description' => \Illuminate\Support\Str::limit((string) ($comic->description ?: 'A comic on '.config('app.name')), 160),
                    'url' => $chapter
                        ? route('comics.read', [$comic->slug, $chapter->number])
                        : route('comics.show', $comic->slug),
                    'image' => $comic->cover()?->url(),
                    'rating' => $comic->rating === 'explicit' || $chapterExplicit ? 'adult' : 'safe',
                    'robots' => $comic->rating === 'explicit' || $chapterExplicit ? 'noindex, nofollow' : $robots,
                ];
            }
        }

        return [
            'robots' => $robots,
            'seo_title' => $seoTitles[$route] ?? config('app.name').' — Art, Comics & Artist Community',
            'description' => $descriptions[$route] ?? config('app.name').' — a gallery and social home for artists who draw what they draw.',
        ];
    }

    private function discoveryRail(Request $request, ?User $viewer): array
    {
        $monthStart = now()->subDays(30);
        $weekStart = now()->subWeek();
        $showExplicit = (bool) $viewer?->show_explicit
            && (! $request->routeIs('gallery') || ! $request->has('explicit') || $request->boolean('explicit'));
        $recentVisiblePosts = fn ($query) => $query
            ->where('created_at', '>=', $monthStart)
            ->visibleRating($showExplicit);

        return [
            'risingArtists' => User::verified()
                ->when($viewer, fn ($query) => $query
                    ->whereKeyNot($viewer->id)
                    ->whereNotIn('users.id', Block::counterpartIds($viewer->id)))
                ->whereHas('posts', $recentVisiblePosts)
                ->withCount([
                    'followers',
                    'posts as recent_posts_count' => $recentVisiblePosts,
                ])
                ->orderByDesc('recent_posts_count')->orderByDesc('followers_count')->orderBy('name')->limit(3)->get()
                ->map(fn (User $artist) => (new UserResource($artist))->resolve() + [
                    'recent_posts_count' => $artist->recent_posts_count,
                ])->all(),
            'popularWorks' => Post::visibleTo($viewer)->visibleRating($showExplicit)
                ->where(fn ($query) => $query->whereHas('likes')->orWhere('posts.likes_count', '>', 0))
                ->withCount([
                    'likes as lifetime_likes', 'comments',
                    'likes as week_likes' => fn ($query) => $query->where('created_at', '>=', $weekStart),
                ])
                ->withExists(['bookmarks as viewer_saved' => fn ($query) => $query->where('user_id', $viewer?->id ?? 0)])
                ->with(['user', 'media.media', 'chapter.comic', 'creditedArtist'])
                ->orderByDesc('week_likes')
                ->orderByRaw('CASE WHEN (SELECT COUNT(*) FROM likes WHERE likes.post_id = posts.id) > posts.likes_count THEN (SELECT COUNT(*) FROM likes WHERE likes.post_id = posts.id) ELSE posts.likes_count END DESC')
                ->limit(3)->get()
                ->map(fn (Post $post) => (new PostResource($post))->resolve($request) + [
                    'lifetime_likes' => max((int) $post->lifetime_likes, (int) $post->likes_count),
                    'week_likes' => $post->week_likes,
                ])->all(),
        ];
    }
}
