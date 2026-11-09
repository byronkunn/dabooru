<?php

namespace App\Http\Controllers;

use App\Http\Resources\PostResource;
use App\Models\PostRating;
use App\Models\Tag;
use App\Models\User;
use App\Support\Rating;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use Inertia\Inertia;

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
    private static function tagList(mixed $value): array
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
    private static function countBound(mixed $value): ?int
    {
        if ($value === null || $value === '' || ! is_numeric($value)) {
            return null;
        }

        $n = (int) $value;

        return $n > 0 ? min($n, 100000) : null;
    }

    /** Read a duration bound from the query, clamped to a sane range. */
    private static function durationBound(mixed $value): ?int
    {
        return is_numeric($value) && (int) $value > 0 ? min(86400, (int) $value) : null;
    }

    public static function filters(array $input, ?User $user): array
    {
        $format = in_array($input['format'] ?? null, self::FORMATS, true) ? $input['format'] : 'all';
        $sort = in_array($input['sort'] ?? null, self::SORTS, true) ? $input['sort'] : 'top';
        $window = in_array($input['window'] ?? null, self::WINDOWS, true) ? $input['window'] : 'week';

        $showExplicit = array_key_exists('explicit', $input)
            ? filter_var($input['explicit'], FILTER_VALIDATE_BOOLEAN)
            : (bool) $user?->show_explicit;
        // optional duration bounds, used by the duration slider
        // 0 and "no upper bound" both mean "unfiltered", so they collapse to null
        $minDuration = self::durationBound($input['min_duration'] ?? null);
        $maxDuration = self::durationBound($input['max_duration'] ?? null);
        if ($maxDuration !== null && $minDuration !== null && $minDuration > $maxDuration) {
            [$minDuration, $maxDuration] = [$maxDuration, $minDuration];
        }

        // A duration bound can only match video. Rather than quietly emptying the
        // feed, narrow the format to video and say so in the UI.
        $hasDuration = $minDuration !== null || $maxDuration !== null;
        $durationNarrowed = $hasDuration && $format !== 'video';
        if ($hasDuration) {
            $format = 'video';
        }

        $minStars = filter_var($input['min_stars'] ?? null, FILTER_VALIDATE_INT);
        $minStars = $minStars !== false && $minStars >= 1 && $minStars <= 5 ? $minStars : null;

        $term = is_string($input['q'] ?? null) ? trim($input['q']) : '';
        $parsed = SearchController::parseOperators($term);
        if ($parsed['type'] && ! $hasDuration) {
            $format = $parsed['type'];
        }

        $tagInput = $input['tag'] ?? null;
        $tag = is_string($tagInput) ? Str::lower(Str::squish($tagInput)) : '';
        $tag = Str::length($tag) <= 40 ? $tag : '';
        $tag = $tag !== '' ? Tag::canonicalName($tag) : '';

        return [[
            'format' => $format,
            'sort' => $sort,
            'window' => $window,
            'min_duration' => $minDuration ?? '',
            'max_duration' => $maxDuration ?? '',
            'tags' => self::tagList($input['tags'] ?? null),
            'exclude_tags' => self::tagList($input['exclude_tags'] ?? null),
            'min_likes' => self::countBound($input['min_likes'] ?? null) ?? '',
            'min_views' => self::countBound($input['min_views'] ?? null) ?? '',
            'min_stars' => $minStars ?? '',
            'explicit' => $showExplicit,
            'q' => $term,
            'tag' => $tag,
        ], $parsed, $durationNarrowed];
    }

    public static function workQuery(?User $user, array $filters, array $parsed): Builder
    {
        $tag = $filters['tag'];

        return SearchController::workQuery($user, $parsed, $parsed['artist'], null, $filters['explicit'])
            ->when($tag !== '', fn ($q) => $q->whereHas('tags', fn ($tags) => $tags->where('name', $tag)))
            ->ofType($filters['format'])
            ->withinWindow($filters['window'])
            ->withinDuration($filters['min_duration'] ?: null, $filters['max_duration'] ?: null)
            ->includingTags($filters['tags'])
            ->excludingTags($filters['exclude_tags'])
            ->aboveThreshold($filters['min_likes'] ?: null, $filters['min_views'] ?: null)
            ->when($filters['min_stars'], fn ($q, $stars) => $q->whereIn('posts.id',
                PostRating::query()->select('post_id')->groupBy('post_id')->havingRaw('AVG(stars) >= ?', [$stars])
            ))
            ->sorted($filters['sort']);
    }

    public function index(Request $request)
    {
        $user = $request->user();
        [$filters, $parsed, $durationNarrowed] = self::filters($request->query(), $user);
        $posts = self::workQuery($user, $filters, $parsed)
            ->with(['user', 'media.media', 'chapter.comic', 'tags'])
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
            'searchTags' => [
                'included' => array_values(array_unique($parsed['tags'])),
                'excluded' => array_values(array_unique($parsed['exclude_tags'])),
            ],
            'filters' => $filters,
            'sortOptions' => [
                ['value' => 'top', 'label' => 'Most liked'],
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
            'durationNarrowed' => $durationNarrowed,
            'tagSuggestions' => Tag::query()
                ->whereNull('merged_into_id')
                ->when(! $filters['explicit'], fn ($q) => $q->where('wiki_rating', '!=', 'explicit'))
                ->withCount('posts')
                ->orderByDesc('posts_count')
                ->take(24)
                ->pluck('name')
                ->all(),
            'ratingLabels' => Rating::LABELS,
        ]);
    }
}
