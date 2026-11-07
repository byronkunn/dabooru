<?php

namespace App\Http\Controllers;

use App\Http\Resources\PostResource;
use App\Models\Post;
use App\Models\Tag;
use App\Support\Rating;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Illuminate\Support\Str;

class GalleryController extends Controller
{
    private const FORMATS = ['all', 'image', 'gif', 'video', 'album', 'comic'];

    private const SORTS = ['top', 'views', 'newest'];

    private const WINDOWS = ['today', 'week', 'month', 'all'];

    /**
     * Normalise a tag list into canonical names.
     *
     * Accepts both shapes a client may send: ?tags=a,b,c (short, shareable) and
     * ?tags[0]=a&tags[1]=b (what Inertia emits for an array in a router.get).
     */
    private function tagList(mixed $value): array
    {
        if ($value === null || $value === '' || $value === []) {
            return [];
        }

        $raw = is_array($value) ? $value : explode(',', (string) $value);

        return collect($raw)
            ->filter(fn ($t) => is_string($t))
            ->map(fn ($t) => Tag::canonicalName(trim($t)))
            ->filter(fn ($t) => $t !== '' && mb_strlen($t) <= 40)
            ->unique()
            ->take(8)
            ->values()
            ->all();
    }

    /** Read a small non-negative count bound (likes, views). */
    private function countBound(mixed $value): ?int
    {
        if ($value === null || $value === '' || ! is_numeric($value)) {
            return null;
        }

        $n = (int) $value;

        return $n > 0 ? min($n, 100000) : null;
    }

    /** Read a duration bound from the query, clamped to a sane range. */
    private function durationBound(mixed $value, ?int $default): ?int
    {
        if ($value === null || $value === '' || ! is_numeric($value)) {
            return $default;
        }

        return max(1, min(3600, (int) $value));
    }

    public function index(Request $request)
    {
        $format = in_array($request->query('format'), self::FORMATS, true) ? $request->query('format') : 'all';
        $sort = in_array($request->query('sort'), self::SORTS, true) ? $request->query('sort') : 'top';
        $window = in_array($request->query('window'), self::WINDOWS, true) ? $request->query('window') : 'week';

        $user = $request->user();
        $showExplicit = $request->has('explicit')
            ? $request->boolean('explicit')
            : ($user ? (bool) $user->show_explicit : false);
        // optional duration bounds, used by the duration slider
        // 0 and "no upper bound" both mean "unfiltered", so they collapse to null
        $minDuration = $this->durationBound($request->query('min_duration'), null);
        $maxDuration = $this->durationBound($request->query('max_duration'), null);
        if ($minDuration !== null && $minDuration <= 0) {
            $minDuration = null;
        }
        if ($maxDuration !== null && $minDuration !== null && $minDuration > $maxDuration) {
            [$minDuration, $maxDuration] = [$maxDuration, $minDuration];
        }

        // A duration bound can only match video. Rather than quietly emptying the
        // feed, narrow the format to video and say so in the UI.
        $durationNarrowed = ($minDuration !== null || $maxDuration !== null) && $format !== 'video';
        if ($durationNarrowed) {
            $format = 'video';
        }

        $includeTags = $this->tagList($request->query('tags'));
        $excludeTags = $this->tagList($request->query('exclude_tags'));

        $minLikes = $this->countBound($request->query('min_likes'));
        $minViews = $this->countBound($request->query('min_views'));

        $term = trim((string) $request->query('q'));

        $tagInput = $request->query('tag');
        $tag = is_string($tagInput) ? Str::lower(Str::squish($tagInput)) : '';
        $tag = Str::length($tag) <= 40 ? $tag : '';
        $tag = $tag !== '' ? Tag::canonicalName($tag) : '';

        $posts = Post::query()
            ->with(['user', 'media.media', 'chapter.comic', 'tags'])
            ->visibleTo($user)
            ->when($tag !== '', fn ($q) => $q->whereHas('tags', fn ($tags) => $tags->where('name', $tag)))
            ->ofType($format)
            ->visibleRating($showExplicit)
            ->withinWindow($window)
            ->withinDuration($minDuration, $maxDuration)
            ->includingTags($includeTags)
            ->excludingTags($excludeTags)
            ->aboveThreshold($minLikes, $minViews)
            ->sorted($sort)
            ->when(mb_strlen($term) >= 2, function ($q) use ($term) {
                $like = '%'.str_replace(['\\', '%'], ['\\\\', '\\%'], $term).'%';
                $q->where(fn ($w) => $w->where('title', 'like', $like)
                    ->orWhere('caption', 'like', $like)
                    ->orWhereHas('user', fn ($u) => $u->where('username', 'like', $like)->orWhere('name', 'like', $like)));
            })
            ->paginate(36)
            ->withQueryString();

        if ($request->expectsJson()) {
            return response()->json([
                'posts' => PostResource::collection($posts->items())->resolve(),
                'page' => $posts->currentPage(),
                'hasMore' => $posts->hasMorePages(),
            ]);
        }

        return Inertia::render('Gallery/Index', [
            'posts' => PostResource::collection($posts->items())->resolve(),
            'page' => $posts->currentPage(),
            'hasMore' => $posts->hasMorePages(),
            'filters' => [
                'format' => $format,
                'sort' => $sort,
                'window' => $window,
                'min_duration' => $minDuration ?? '',
                'max_duration' => $maxDuration ?? '',
                'tags' => $includeTags,
                'exclude_tags' => $excludeTags,
                'min_likes' => $minLikes ?? '',
                'min_views' => $minViews ?? '',
                'explicit' => $showExplicit,
                'q' => $term,
                'tag' => $tag,
            ],
            'stats' => [
                'works' => Post::count(),
                'artists' => \App\Models\User::verified()->has('posts')->count(),
            ],
            // real credited artists only: never the "unsorted" bucket
            'suggested' => \App\Http\Resources\UserResource::collection(
                \App\Models\User::verified()
                    ->when($user, fn ($q) => $q->whereKeyNot($user->id))
                    ->withCount(['followers', 'posts'])
                    ->orderByDesc('posts_count')
                    ->orderBy('name')
                    ->take(3)
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
            'durationBounds' => [0, 60],
            'durationNarrowed' => $durationNarrowed,
            'tagSuggestions' => Tag::query()
                ->withCount('posts')
                ->orderByDesc('posts_count')
                ->take(24)
                ->pluck('name')
                ->all(),
            'ratingLabels' => Rating::LABELS,
        ]);
    }
}
