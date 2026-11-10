<?php

namespace App\Support;

use App\Models\Comic;
use App\Models\ComicChapter;
use App\Models\Post;
use App\Models\User;
use Carbon\CarbonInterface;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\DB;

class StudioAnalytics
{
    public static function works(User $user, string $ownership): Builder
    {
        return Post::where('user_id', $user->id)
            ->when($ownership === 'original', fn ($query) => $query->where('is_original', true)
                ->where(fn ($credit) => $credit->whereNull('artist_name')->orWhere('artist_name', '')))
            ->when($ownership === 'shared', fn ($query) => $query->where(fn ($credit) => $credit
                ->where('is_original', false)->orWhereNotNull('artist_name')->where('artist_name', '!=', '')));
    }

    public static function ranked(Builder $works, CarbonInterface $start, CarbonInterface $end, string $sort): Builder
    {
        $query = (clone $works)->with(['media.media', 'tags'])
            ->withCount([
                'bookmarks', 'starRatings',
                'likes as period_likes' => fn ($q) => $q->whereBetween('created_at', [$start, $end]),
                'bookmarks as period_saves' => fn ($q) => $q->whereBetween('created_at', [$start, $end]),
                'comments as period_comments' => fn ($q) => $q->whereBetween('created_at', [$start, $end]),
            ])->withAvg('starRatings', 'stars')
            ->selectSub(DB::table('post_views')->whereColumn('post_id', 'posts.id')
                ->whereBetween('viewed_on', [$start->toDateString(), $end->toDateString()])->selectRaw('COUNT(*)'), 'period_views')
            ->selectSub(DB::table('likes')->whereColumn('post_id', 'posts.id')
                ->whereBetween('created_at', [$end->copy()->subDay(), $end])->selectRaw('COUNT(*)'), 'recent_likes')
            ->selectSub(DB::table('likes')->whereColumn('post_id', 'posts.id')
                ->where('created_at', '>=', $end->copy()->subDays(2))->where('created_at', '<', $end->copy()->subDay())
                ->selectRaw('COUNT(*)'), 'previous_likes');
        match ($sort) {
            'views' => $query->orderByDesc('period_views'),
            'saves' => $query->orderByDesc('period_saves'),
            'likes' => $query->orderByDesc('period_likes'),
            'ratings' => $query->orderByDesc('star_ratings_avg_stars')->orderByDesc('star_ratings_count'),
            // PostgreSQL only permits a SELECT alias as a bare ORDER BY value,
            // not as part of an expression. Repeat the correlated counts here
            // so both rankings work on PostgreSQL as well as SQLite.
            'engagement' => $query->orderByRaw('(
                (SELECT COUNT(*) FROM likes WHERE likes.post_id = posts.id AND likes.created_at BETWEEN ? AND ?)
                + (SELECT COUNT(*) FROM bookmarks WHERE bookmarks.post_id = posts.id AND bookmarks.created_at BETWEEN ? AND ?)
                + (SELECT COUNT(*) FROM comments WHERE comments.post_id = posts.id AND comments.deleted_at IS NULL AND comments.created_at BETWEEN ? AND ?)
            ) DESC', [$start, $end, $start, $end, $start, $end]),
            'growth' => $query->orderByRaw('(
                (SELECT COUNT(*) FROM likes WHERE likes.post_id = posts.id AND likes.created_at BETWEEN ? AND ?)
                - (SELECT COUNT(*) FROM likes WHERE likes.post_id = posts.id AND likes.created_at >= ? AND likes.created_at < ?)
            ) DESC', [$end->copy()->subDay(), $end, $end->copy()->subDays(2), $end->copy()->subDay()]),
            default => $query->orderByDesc('created_at'),
        };
        return $query->orderByDesc('id');
    }

    public static function card(Post $post): array
    {
        $cover = $post->media->first()?->media;
        $interactions = (int) $post->period_likes + (int) $post->period_saves + (int) $post->period_comments;
        return [
            'id' => $post->id, 'title' => $post->title ?: 'Untitled', 'type' => $post->type,
            'original' => ! $post->isExternalArtist(), 'artist' => $post->artist_name,
            'url' => route('posts.show', $post->id, false),
            'thumbnail' => $cover ? ($cover->thumbUrl() ?: ($cover->kind === 'image' ? $cover->url() : null)) : null,
            'views' => (int) $post->views, 'likes' => (int) $post->likes_count,
            'saves' => (int) $post->bookmarks_count, 'comments' => (int) $post->comments_count,
            'rating' => $post->star_ratings_avg_stars === null ? null : round((float) $post->star_ratings_avg_stars, 1),
            'votes' => (int) $post->star_ratings_count,
            'periodViews' => (int) $post->period_views, 'periodLikes' => (int) $post->period_likes,
            'periodSaves' => (int) $post->period_saves, 'periodComments' => (int) $post->period_comments,
            'interactionsPer100Views' => $post->period_views ? round(100 * $interactions / $post->period_views, 1) : null,
            'growth' => (int) $post->recent_likes - (int) $post->previous_likes,
        ];
    }

    public static function dashboard(User $user, int $days, string $ownership, ?int $postId): array
    {
        $end = now();
        $start = $end->copy()->startOfDay()->subDays($days - 1);
        $previousStart = $start->copy()->subDays($days);
        $works = self::works($user, $ownership);
        if ($postId) $works->whereKey($postId)->firstOrFail();
        $ids = (clone $works)->select('posts.id');
        $dates = collect(range(0, $days - 1))->map(fn ($i) => $start->copy()->addDays($i)->toDateString());
        $definitions = [
            'views' => ['Daily work views', 'post_views', 'viewed_on'],
            'likes' => ['Likes', 'likes', 'created_at'],
            'saves' => ['Saves', 'bookmarks', 'created_at'],
            'comments' => ['Comments', 'comments', 'created_at'],
            'ratings' => ['Star votes', 'post_ratings', 'created_at'],
        ];
        $metrics = [];
        foreach ($definitions as $key => [$label, $table, $column]) {
            $query = DB::table($table)->whereIn('post_id', $ids);
            if ($table === 'comments') $query->whereNull('deleted_at');
            $currentStart = $column === 'viewed_on' ? $start->toDateString() : $start;
            $currentEnd = $column === 'viewed_on' ? $end->toDateString() : $end;
            $daily = (clone $query)->whereBetween($column, [$currentStart, $currentEnd])
                ->selectRaw("DATE({$column}) as day, COUNT(*) as total")->groupByRaw("DATE({$column})")->pluck('total', 'day');
            $values = $dates->map(fn ($date) => (int) $daily->get($date, 0));
            $previous = (clone $query)->where($column, '>=', $column === 'viewed_on' ? $previousStart->toDateString() : $previousStart)
                ->where($column, '<', $currentStart)->count();
            $metrics[$key] = ['label' => $label, 'total' => $values->sum(), 'previous' => $previous, 'daily' => $values];
        }
        $changes = DB::table('follower_changes')->where('user_id', $user->id);
        $growth = (clone $changes)->whereBetween('created_at', [$start, $end])->sum('change');
        $previousGrowth = (clone $changes)->where('created_at', '>=', $previousStart)->where('created_at', '<', $start)->sum('change');
        $growthDaily = (clone $changes)->whereBetween('created_at', [$start, $end])->selectRaw('DATE(created_at) as day, SUM(change) as total')
            ->groupByRaw('DATE(created_at)')->pluck('total', 'day');
        $metrics['followers'] = ['label' => 'Net follower change (account)', 'total' => (int) $growth, 'previous' => (int) $previousGrowth,
            'daily' => $dates->map(fn ($date) => (int) $growthDaily->get($date, 0))];

        $viewQuery = DB::table('post_views')->whereIn('post_id', $ids)->whereBetween('viewed_on', [$start->toDateString(), $end->toDateString()]);
        $sources = (clone $viewQuery)->selectRaw('source, COUNT(*) as views')->groupBy('source')->orderByDesc('views')->get();
        $members = (clone $viewQuery)->where('viewer_key', 'like', 'u%')->distinct()->count('viewer_key');
        $lifetime = (clone $works)->selectRaw('COUNT(*) as works, COALESCE(SUM(views),0) as views, COALESCE(SUM(likes_count),0) as likes, COALESCE(SUM(comments_count),0) as comments')->first();
        $lifetimeSaves = DB::table('bookmarks')->whereIn('post_id', $ids)->count();
        $rating = DB::table('post_ratings')->whereIn('post_id', $ids)->avg('stars');

        // Use the same ownership-filtered work IDs for every tag/format comparison.
        $breakdowns = [];
        foreach (['tags' => 'tags.name', 'formats' => 'posts.type'] as $kind => $column) {
            $query = DB::table('posts')->whereIn('posts.id', $ids);
            if ($kind === 'tags') $query->join('post_tag', 'posts.id', '=', 'post_tag.post_id')->join('tags', 'tags.id', '=', 'post_tag.tag_id');
            $breakdowns[$kind] = $query->leftJoin('likes', fn ($join) => $join->on('likes.post_id', '=', 'posts.id')
                ->whereBetween('likes.created_at', [$start, $end]))
                ->selectRaw("{$column} as label, COUNT(DISTINCT posts.id) as works, COUNT(likes.id) as likes")
                ->groupBy($column)->orderByDesc('likes')->limit(10)->get();
        }
        $posting = (clone $works)->withCount(['likes as period_likes' => fn ($q) => $q->whereBetween('created_at', [$start, $end])])
            ->whereBetween('created_at', [$start, $end])->get(['id', 'created_at']);
        $hours = $posting->groupBy(fn ($post) => $post->created_at->format('H'))->map(fn ($group, $hour) => [
            'hour' => (int) $hour, 'posts' => $group->count(), 'averageLikes' => round($group->avg('period_likes'), 1),
        ])->sortByDesc('averageLikes')->values();
        $suggestion = $posting->count() >= 10 && $posting->sum('period_likes') >= 20
            ? $hours->first(fn ($hour) => $hour['posts'] >= 3) : null;

        return [
            'days' => $days, 'ownership' => $ownership, 'selectedPost' => $postId, 'dates' => $dates, 'metrics' => $metrics,
            'lifetime' => ['works' => (int) $lifetime->works, 'views' => (int) $lifetime->views, 'likes' => (int) $lifetime->likes,
                'comments' => (int) $lifetime->comments, 'saves' => $lifetimeSaves, 'rating' => $rating === null ? null : round($rating, 1),
                'followers' => $user->followers()->count()],
            'sources' => $sources, 'uniqueMembers' => $members, 'breakdowns' => $breakdowns, 'postingHours' => $hours,
            'postingSuggestion' => $suggestion, 'timezone' => config('app.timezone'),
            'trackingSince' => DB::table('studio_tracking')->value('started_at'),
            'topPosts' => self::ranked($works, $start, $end, 'engagement')->limit(6)->get()->map(self::card(...)),
        ];
    }

    public static function comics(User $user): array
    {
        $series = Comic::where('user_id', $user->id)->withCount('follows')->orderByDesc('id')
            ->simplePaginate(10, ['*'], 'comics_page')->withQueryString();
        $data = $series->getCollection()->map(function ($comic) {
            $chapters = ComicChapter::where('comic_id', $comic->id)->withCount('pages')->orderBy('number')->get();
            $readers = DB::table('chapter_readers')->whereIn('comic_chapter_id', $chapters->pluck('id'))->get();
            return [
                'id' => $comic->id, 'title' => $comic->title, 'status' => $comic->status, 'followers' => $comic->follows_count,
                'url' => route('comics.show', $comic->slug, false),
                'chapters' => $chapters->map(function ($chapter) use ($readers, $comic) {
                    $group = $readers->where('comic_chapter_id', $chapter->id);
                    $members = $group->filter(fn ($reader) => str_starts_with($reader->viewer_key, 'u'));
                    $completed = $members->whereNotNull('completed_at')->count();
                    $pages = $chapter->pages_count;
                    return [
                        'id' => $chapter->id, 'number' => $chapter->number, 'title' => $chapter->title,
                        'draft' => ! $chapter->published_at, 'views' => $chapter->views, 'readers' => $group->count(),
                        'memberReaders' => $members->count(),
                        'completed' => $completed, 'completionRate' => $members->count() ? round(100 * $completed / $members->count(), 1) : null,
                        'url' => $chapter->published_at ? route('comics.read', [$comic->slug, $chapter->number], false) : null,
                        'dropOff' => $members->whereNull('completed_at')->groupBy('max_page')->map(fn ($at, $page) => [
                            'page' => (int) $page, 'readers' => $at->count(),
                        ])->sortBy('page')->values(),
                        'pages' => $pages,
                    ];
                }),
            ];
        })->all();
        return ['series' => $data, 'nextPage' => $series->nextPageUrl(), 'previousPage' => $series->previousPageUrl(),
            'trackingSince' => DB::table('studio_tracking')->value('started_at')];
    }
}
