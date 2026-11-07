<?php

namespace App\Http\Controllers;

use App\Http\Resources\PostResource;
use App\Http\Resources\UserResource;
use App\Models\Comic;
use App\Models\Post;
use App\Models\User;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class SearchController extends Controller
{
    /** Minimum query length; shorter terms are ignored rather than searched. */
    public const MIN_LENGTH = 2;

    /** How many live suggestions the header dropdown shows. */
    private const SUGGEST_LIMIT = 5;

    public function index(Request $request): Response
    {
        $term = trim((string) $request->query('q'));
        $tab = in_array($request->query('tab'), ['works', 'comics', 'people'], true)
            ? $request->query('tab')
            : 'works';

        $artist = $request->query('artist');
        $type = $request->query('type');

        $user = $request->user();
        $showExplicit = $user ? (bool) $user->show_explicit : false;

        $searchable = mb_strlen($term) >= self::MIN_LENGTH;
        // Only % and backslash need escaping. Underscore is a wildcard in MySQL's LIKE
        // but not in SQLite unless ESCAPE is declared, and declaring ESCAPE is
        // not portable across MySQL/PostgreSQL — so handles like "rin_senpai"
        // match literally on every driver we support.
        $escape = fn (string $s) => str_replace(['\\', '%'], ['\\\\', '\\%'], $s);
        $like = '%'.$escape($term).'%';
        $results = [
            'term' => $term,
            'tab' => $tab,
            'searchable' => $searchable,
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
        $works = Post::query()
            ->with(['user', 'media.media', 'chapter.comic'])
            ->visibleRating($showExplicit)
            ->where(function ($q) use ($like) {
                $q->where('title', 'like', $like)
                    ->orWhere('caption', 'like', $like)
                    ->orWhereHas('user', fn ($u) => $u->where('username', 'like', $like)->orWhere('name', 'like', $like))
                    ->orWhereHas('chapter.comic', fn ($c) => $c->where('title', 'like', $like));
            })
            ->when($artist, fn ($q) => $q->whereHas('user', fn ($u) => $u->where('username', $artist)))
            ->when($results['type'], fn ($q, $t) => $q->where('type', $t))
            ->orderByDesc('likes_count')
            ->orderByDesc('created_at');

        $results['counts']['works'] = (clone $works)->count();
        $results['works'] = PostResource::collection($works->take(48)->get())->resolve();

        $results['artistOptions'] = (clone $works)
            ->with('user:id,username,name')
            ->get()
            ->pluck('user')
            ->filter()
            ->unique('username')
            ->take(8)
            ->map(fn ($u) => ['username' => $u->username, 'name' => $u->name])
            ->values()
            ->all();

        // ---- comics ------------------------------------------------------
        $comics = Comic::query()
            ->with(['user', 'chapters' => fn ($q) => $q->with('pages.media')])
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
            'chapters' => $c->chapters->count(),
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
     * Live suggestions for the header search box — people first, then works,
     * then comics. Returns small JSON, never the full result set.
     */
    public function suggest(Request $request)
    {
        $term = trim((string) $request->query('q'));

        if (mb_strlen($term) < self::MIN_LENGTH) {
            return response()->json(['people' => [], 'works' => [], 'comics' => []]);
        }

        // Only % and backslash need escaping. Underscore is a wildcard in MySQL's LIKE
        // but not in SQLite unless ESCAPE is declared, and declaring ESCAPE is
        // not portable across MySQL/PostgreSQL — so handles like "rin_senpai"
        // match literally on every driver we support.
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
                'url' => route('profile.show', $u->username, false),
            ]);

        $works = Post::with('media.media')
            ->visibleRating($showExplicit)
            ->where(fn ($q) => $q->where('title', 'like', $like)->orWhere('caption', 'like', $like))
            ->orderByDesc('likes_count')
            ->take(self::SUGGEST_LIMIT)
            ->get()
            ->map(fn (Post $p) => [
                'label' => $p->title ?: mb_strimwidth((string) $p->caption, 0, 50, '…'),
                'hint' => ucfirst($p->type),
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
                'avatar' => $c->cover()?->thumbUrl(),
                'url' => route('comics.show', $c->slug, false),
            ]);

        return response()->json([
            'people' => $people,
            'works' => $works,
            'comics' => $comics,
            'more' => route('search', ['q' => $term], false),
        ]);
    }
}
