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
                ->orderByRaw('CASE WHEN lifetime_likes > posts.likes_count THEN lifetime_likes ELSE posts.likes_count END DESC')
                ->limit(3)->get()
                ->map(fn (Post $post) => (new PostResource($post))->resolve($request) + [
                    'lifetime_likes' => max((int) $post->lifetime_likes, (int) $post->likes_count),
                    'week_likes' => $post->week_likes,
                ])->all(),
        ];
    }
}
