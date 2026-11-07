<?php

namespace App\Http\Controllers;

use App\Http\Resources\PostResource;
use App\Models\Post;
use App\Support\Rating;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class GalleryController extends Controller
{
    private const FORMATS = ['all', 'image', 'gif', 'video', 'album', 'comic'];

    private const SORTS = ['top', 'views', 'newest'];

    private const WINDOWS = ['today', 'week', 'month', 'all'];

    public function index(Request $request): Response
    {
        $format = in_array($request->query('format'), self::FORMATS, true) ? $request->query('format') : 'all';
        $sort = in_array($request->query('sort'), self::SORTS, true) ? $request->query('sort') : 'top';
        $window = in_array($request->query('window'), self::WINDOWS, true) ? $request->query('window') : 'week';

        $user = $request->user();
        $showExplicit = $user ? (bool) $user->show_explicit : $request->boolean('explicit', false);
        $term = trim((string) $request->query('q'));

        $posts = Post::query()
            ->with(['user', 'media.media', 'chapter.comic'])
            ->ofType($format)
            ->visibleRating($showExplicit)
            ->withinWindow($window)
            ->sorted($sort)
            ->when(mb_strlen($term) >= 2, function ($q) use ($term) {
                $like = '%'.str_replace(['\\', '%'], ['\\\\', '\\%'], $term).'%';
                $q->where(fn ($w) => $w->where('title', 'like', $like)
                    ->orWhere('caption', 'like', $like)
                    ->orWhereHas('user', fn ($u) => $u->where('username', 'like', $like)->orWhere('name', 'like', $like)));
            })
            ->paginate(36)
            ->withQueryString();

        return Inertia::render('Gallery/Index', [
            'posts' => PostResource::collection($posts->items())->resolve(),
            'page' => $posts->currentPage(),
            'hasMore' => $posts->hasMorePages(),
            'filters' => [
                'format' => $format,
                'sort' => $sort,
                'window' => $window,
                'explicit' => $showExplicit,
                'q' => $term,
            ],
            'stats' => [
                'works' => Post::count(),
                'artists' => \App\Models\User::verified()->count(),
            ],
            'trendingTags' => ['#neon', '#sketch', '#webtoon', '#yuri', '#romance', '#oc', '#comic', '#lineart'],
            'suggested' => \App\Http\Resources\UserResource::collection(
                \App\Models\User::verified()
                    ->whereKeyNot($user?->id ?? 0)
                    ->inRandomOrder()
                    ->take(3)
                    ->withCount(['followers', 'posts'])
                    ->get()
            )->resolve(),
            'sortOptions' => [
                ['value' => 'top', 'label' => 'Top rated'],
                ['value' => 'views', 'label' => 'Most viewed'],
                ['value' => 'newest', 'label' => 'Newest'],
            ],
            'windowOptions' => [
                ['value' => 'today', 'label' => 'Today'],
                ['value' => 'week', 'label' => 'This week'],
                ['value' => 'month', 'label' => 'This month'],
                ['value' => 'all', 'label' => 'All time'],
            ],
            'formatOptions' => [
                ['value' => 'all', 'label' => 'All'],
                ['value' => 'image', 'label' => 'Images'],
                ['value' => 'gif', 'label' => 'GIFs'],
                ['value' => 'video', 'label' => 'Videos'],
                ['value' => 'album', 'label' => 'Albums'],
                ['value' => 'comic', 'label' => 'Comics'],
            ],
            'ratingLabels' => Rating::LABELS,
        ]);
    }
}
