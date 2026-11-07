<?php

namespace App\Http\Controllers;

use App\Http\Resources\PostResource;
use App\Http\Resources\UserResource;
use App\Models\Comic;
use App\Models\Post;
use App\Models\Tag;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use Inertia\Inertia;
use Inertia\Response;

class SearchController extends Controller
{
    /** Minimum query length; shorter terms are ignored rather than searched. */
    public const MIN_LENGTH = 2;

    /** How many live suggestions the header dropdown shows. */
    private const SUGGEST_LIMIT = 5;

    public static function parseOperators(string $input): array
    {
        preg_match_all('/(?:[^\s"\']+|"[^"]*"|\'[^\']*\')+/', trim($input), $matches);
        $tokens = $matches[0];
        $textParts = [];
        $parsed = [
            'raw' => $input,
            'text' => '',
            'type' => null,
            'rating' => null,
            'artist' => null,
            'tags' => [],
            'exclude_tags' => [],
        ];

        foreach ($tokens as $token) {
            if ($token === '') continue;

            if (preg_match('/^(?:by|artist):(.+)$/i', $token, $m)) {
                $parsed['artist'] = trim($m[1], '"\'');
            } elseif (preg_match('/^type:(image|gif|video|album|comic)$/i', $token, $m)) {
                $parsed['type'] = strtolower($m[1]);
            } elseif (preg_match('/^rating:(sfw|suggestive|explicit)$/i', $token, $m)) {
                $parsed['rating'] = strtolower($m[1]);
            } elseif (preg_match('/^tag:(.+)$/i', $token, $m)) {
                $parsed['tags'][] = Tag::canonicalName(ltrim(trim($m[1], '"\''), '#'));
            } elseif (preg_match('/^-tag:(.+)$/i', $token, $m)) {
                $parsed['exclude_tags'][] = Tag::canonicalName(ltrim(trim($m[1], '"\''), '#'));
            } elseif (preg_match('/^-(?:#)?([a-z0-9_\-]+)$/i', $token, $m)) {
                $parsed['exclude_tags'][] = Tag::canonicalName($m[1]);
            } elseif (str_starts_with($token, '#') && strlen($token) > 1) {
                $parsed['tags'][] = Tag::canonicalName(trim(substr($token, 1), '"\''));
            } else {
                $textParts[] = $token;
            }
        }

        $parsed['text'] = implode(' ', $textParts);

        return $parsed;
    }

    public static function workQuery(?User $user, array $parsed, ?string $artist, ?string $type): \Illuminate\Database\Eloquent\Builder
    {
        $term = $parsed['text'];
        $rating = $parsed['rating'];
        $showExplicit = (bool) $user?->show_explicit;
        $like = '%'.str_replace(['\\', '%'], ['\\\\', '\\%'], $term).'%';

        $works = Post::query()
            ->visibleRating($showExplicit)
            ->visibleTo($user);

        if ($term !== '') {
            $works->where(function ($q) use ($like, $term) {
                $q->where('title', 'like', $like)
                    ->orWhere('caption', 'like', $like)
                    ->orWhere('artist_name', 'like', $like)
                    ->orWhereHas('tags', fn ($tags) => $tags->where('name', Tag::canonicalName($term)))
                    ->orWhereHas('user', fn ($u) => $u->where('username', 'like', $like)->orWhere('name', 'like', $like))
                    ->orWhereHas('chapter.comic', fn ($c) => $c->where('title', 'like', $like));
            });
        }

        if ($artist) {
            $works->where(fn ($q) => $q->whereHas('user', fn ($u) => $u->where('username', $artist)->orWhere('name', 'like', '%'.$artist.'%'))
                ->orWhere('artist_name', 'like', '%'.$artist.'%'));
        }

        if ($type) {
            $works->where('type', $type);
        }

        if ($rating) {
            $works->where('rating', $rating);
        }

        foreach ($parsed['tags'] as $tag) {
            $works->whereHas('tags', fn ($t) => $t->where('name', $tag));
        }

        foreach ($parsed['exclude_tags'] as $exTag) {
            $works->whereDoesntHave('tags', fn ($t) => $t->where('name', $exTag));
        }

        return $works;
    }

    public function index(Request $request): Response
    {
        $rawTerm = trim((string) $request->query('q'));
        $parsed = self::parseOperators($rawTerm);
        $term = $parsed['text'];

        $tab = in_array($request->query('tab'), ['works', 'comics', 'people'], true)
            ? $request->query('tab')
            : 'works';

        $artistFilter = $request->query('artist');
        $typeFilter = $request->query('type');
        $artist = is_string($artistFilter) && $artistFilter !== '' ? $artistFilter : $parsed['artist'];
        $type = in_array($typeFilter, Post::TYPES, true) ? $typeFilter : $parsed['type'];
        $rating = $parsed['rating'];

        $user = $request->user();
        $showExplicit = $user ? (bool) $user->show_explicit : false;

        $hasOperators = ! empty($parsed['tags']) || ! empty($parsed['exclude_tags']) || ! empty($artist) || ! empty($type) || ! empty($rating);
        $searchable = mb_strlen($term) >= self::MIN_LENGTH || $hasOperators;
        $escape = fn (string $s) => str_replace(['\\', '%'], ['\\\\', '\\%'], $s);
        $like = '%'.$escape($term).'%';

        $results = [
            'term' => $rawTerm,
            'tab' => $tab,
            'searchable' => $searchable,
            'parsed' => $parsed,
            'workFilters' => ['q' => $rawTerm, 'artist' => $artist, 'type' => in_array($type, Post::TYPES, true) ? $type : null],
            'counts' => ['works' => 0, 'comics' => 0, 'people' => 0],
            'works' => [],
            'comics' => [],
            'people' => [],
            'artist' => null,
            'type' => in_array($type, Post::TYPES, true) ? $type : null,
            'artistOptions' => [],
        ];

        if (! $searchable) {
            return Inertia::render('Search/Index', $results + [
                'minLength' => self::MIN_LENGTH,
            ]);
        }

        // ---- works -------------------------------------------------------
        $works = self::workQuery($user, $parsed, $artist, $results['type'])
            ->with(['user', 'media.media', 'chapter.comic']);
        $works->orderByDesc('likes_count')->orderByDesc('created_at');

        $results['counts']['works'] = (clone $works)->count();
        $results['works'] = PostResource::collection($works->take(48)->get())->resolve();

        $artistIds = (clone $works)
            ->reorder()
            ->select('user_id')
            ->whereNotNull('user_id')
            ->distinct()
            ->limit(8)
            ->pluck('user_id');
        $results['artistOptions'] = User::query()
            ->whereIn('id', $artistIds)
            ->get(['id', 'username', 'name'])
            ->map(fn ($u) => ['username' => $u->username, 'name' => $u->name])
            ->values()
            ->all();

        // ---- comics ------------------------------------------------------
        $comics = Comic::query()
            ->withCount('chapters')
            ->with(['user', 'coverMedia', 'chapters' => fn ($q) => $q->limit(1)->with(['pages' => fn ($p) => $p->limit(1)->with('media')])])
            ->when(! $showExplicit, fn ($q) => $q->where('rating', '!=', 'explicit'))
            ->where(function ($q) use ($like) {
                $q->where('title', 'like', $like)
                    ->orWhere('description', 'like', $like)
                    ->orWhereHas('user', fn ($u) => $u->where('username', 'like', $like)->orWhere('name', 'like', $like));
            })
            ->latest('last_chapter_at');

        $results['counts']['comics'] = (clone $comics)->count();
        $results['comics'] = $comics->take(24)->get()->map(fn (Comic $c) => [
            'id' => $c->id,
            'slug' => $c->slug,
            'title' => $c->title,
            'description' => $c->description,
            'rating' => $c->rating,
            'status' => $c->status,
            'author' => $c->user->name,
            'author_username' => $c->user->username,
            'cover' => $c->cover()?->thumbUrl(),
            'chapters' => $c->chapters_count,
            'url' => route('comics.show', $c->slug, false),
        ])->all();

        // ---- people ------------------------------------------------------
        $people = User::query()
            ->verified()
            ->where(function ($q) use ($like) {
                $q->where('username', 'like', $like)->orWhere('name', 'like', $like);
            })
            ->withCount(['followers', 'posts'])
            ->orderByDesc('followers_count')
            ->orderBy('username')
            ->take(24);

        $results['counts']['people'] = User::query()
            ->verified()
            ->where(fn ($q) => $q->where('username', 'like', $like)->orWhere('name', 'like', $like))
            ->count();
        $results['people'] = UserResource::collection($people->get())->resolve();

        $results['artist'] = $artist
            ? User::where('username', $artist)->first()?->only(['username', 'name'])
            : null;

        return Inertia::render('Search/Index', $results + [
            'minLength' => self::MIN_LENGTH,
        ]);
    }

    /**
     * Live suggestions for the header search box: people, external artists,
     * tags, works, and comics. When term is empty, returns trending tags.
     */
    public function suggest(Request $request)
    {
        $rawTerm = trim((string) $request->query('q'));
        $parsed = self::parseOperators($rawTerm);
        $term = $parsed['text'] !== '' ? $parsed['text'] : (! empty($parsed['tags']) ? $parsed['tags'][0] : $rawTerm);

        // When term is empty, return trending tags
        if (mb_strlen($term) < self::MIN_LENGTH) {
            $trending = Tag::whereNull('merged_into_id')
                ->when(! $request->user()?->show_explicit, fn ($q) => $q->where('wiki_rating', '!=', 'explicit'))
                ->withCount(['posts' => fn ($q) => $q->visibleTo($request->user())->visibleRating((bool) $request->user()?->show_explicit)])
                ->orderByDesc('posts_count')
                ->take(8)
                ->get()
                ->map(fn (Tag $t) => [
                    'label' => '#'.$t->name,
                    'hint' => $t->posts_count.' works',
                    'tag' => $t->name,
                    'url' => route('gallery', ['tag' => $t->name, 'window' => 'all'], false),
                ]);

            return response()->json([
                'people' => [],
                'external_artists' => [],
                'tags' => [],
                'works' => [],
                'comics' => [],
                'trending' => $trending,
            ]);
        }

        $escape = fn (string $s) => str_replace(['\\', '%'], ['\\\\', '\\%'], $s);
        $like = '%'.$escape($term).'%';
        $showExplicit = $request->user() ? (bool) $request->user()->show_explicit : false;

        $people = User::verified()
            ->where(fn ($q) => $q->where('username', 'like', $like)->orWhere('name', 'like', $like))
            ->take(self::SUGGEST_LIMIT)
            ->get()
            ->map(fn (User $u) => [
                'label' => $u->name,
                'hint' => '@'.$u->username,
                'avatar' => $u->avatarUrl(),
                'type' => 'artist',
                'url' => route('profile.show', $u->username, false),
            ]);

        $externalArtists = Post::whereNotNull('artist_name')
            ->visibleTo($request->user())->visibleRating($showExplicit)
            ->where('artist_name', 'like', $like)
            ->select('artist_name')
            ->distinct()
            ->take(self::SUGGEST_LIMIT)
            ->get()
            ->map(fn ($p) => [
                'label' => $p->artist_name,
                'hint' => 'External artist',
                'is_external' => true,
                'type' => 'external_artist',
                'url' => route('search', ['q' => 'by:"'.$p->artist_name.'"'], false),
            ]);

        $cleanTag = ltrim($term, '#');
        $tags = Tag::whereNull('merged_into_id')
            ->when(! $showExplicit, fn ($q) => $q->where('wiki_rating', '!=', 'explicit'))
            ->where(fn ($q) => $q->where('name', 'like', '%'.$escape($cleanTag).'%')
                ->orWhereIn('id', \Illuminate\Support\Facades\DB::table('tag_aliases')->where('name', 'like', '%'.$escape($cleanTag).'%')->select('tag_id')))
            ->withCount(['posts' => fn ($q) => $q->visibleTo($request->user())->visibleRating($showExplicit)])
            ->orderByDesc('posts_count')
            ->take(self::SUGGEST_LIMIT)
            ->get()
            ->map(fn (Tag $t) => [
                'label' => '#'.$t->name,
                'hint' => $t->category.' · '.$t->posts_count.' works',
                'category' => $t->category,
                'tag' => $t->name,
                'type' => 'tag',
                'url' => route('knowledge.show', ['type' => 'tag', 'id' => $t->id], false),
            ]);

        $works = Post::with(['media.media', 'user'])
            ->visibleRating($showExplicit)
            ->visibleTo($request->user())
            ->where(fn ($q) => $q->where('title', 'like', $like)->orWhere('caption', 'like', $like)->orWhere('artist_name', 'like', $like))
            ->orderByDesc('likes_count')
            ->take(self::SUGGEST_LIMIT)
            ->get()
            ->map(fn (Post $p) => [
                'label' => $p->title ?: mb_strimwidth((string) $p->caption, 0, 50, '…'),
                'hint' => $p->artist_name ? 'By '.$p->artist_name : ($p->user ? '@'.$p->user->username : ucfirst($p->type)),
                'badge' => ucfirst($p->type),
                'type' => 'work',
                'avatar' => ($cover = $p->cover()) ? $cover->thumbUrl() : null,
                'url' => route('posts.show', $p->id, false),
            ]);

        $comics = Comic::where('title', 'like', $like)
            ->when(! $showExplicit, fn ($q) => $q->where('rating', '!=', 'explicit'))
            ->take(self::SUGGEST_LIMIT)
            ->get()
            ->map(fn (Comic $c) => [
                'label' => $c->title,
                'hint' => 'Comic',
                'badge' => 'Comic',
                'type' => 'comic',
                'avatar' => $c->cover()?->thumbUrl(),
                'url' => route('comics.show', $c->slug, false),
            ]);

        return response()->json([
            'people' => $people,
            'external_artists' => $externalArtists,
            'tags' => $tags,
            'works' => $works,
            'comics' => $comics,
            'trending' => [],
            'more' => route('search', ['q' => $rawTerm], false),
        ]);
    }

    public function trending(Request $request)
    {
        $tags = Tag::whereNull('merged_into_id')
            ->when(! $request->user()?->show_explicit, fn ($q) => $q->where('wiki_rating', '!=', 'explicit'))
            ->withCount(['posts' => fn ($q) => $q->visibleTo($request->user())->visibleRating((bool) $request->user()?->show_explicit)])
            ->orderByDesc('posts_count')
            ->take(10)
            ->get()
            ->map(fn (Tag $t) => [
                'label' => '#'.$t->name,
                'hint' => $t->posts_count.' works',
                'tag' => $t->name,
                'url' => route('gallery', ['tag' => $t->name, 'window' => 'all'], false),
            ]);

        return response()->json(['tags' => $tags]);
    }
}
