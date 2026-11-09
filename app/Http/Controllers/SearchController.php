<?php

namespace App\Http\Controllers;

use App\Models\Comic;
use App\Models\Post;
use App\Models\Tag;
use App\Models\User;
use App\Models\WikiPage;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

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
                $textParts[] = trim($token, '"\'');
            }
        }

        $parsed['text'] = implode(' ', $textParts);

        return $parsed;
    }

    public static function workQuery(?User $user, array $parsed, ?string $artist, ?string $type, ?bool $showExplicit = null): \Illuminate\Database\Eloquent\Builder
    {
        $term = $parsed['text'];
        $rating = $parsed['rating'];
        $showExplicit ??= (bool) $user?->show_explicit;
        $like = '%'.str_replace(['\\', '%'], ['\\\\', '\\%'], $term).'%';

        $works = Post::query()
            ->visibleRating($showExplicit)
            ->visibleTo($user)
            // account-level tag mutes hide those works from every search surface
            ->excludingTags($user ? \App\Support\CollectionLibrary::mutedFor($user) : []);

        if ($term !== '') {
            $works->where(function ($q) use ($like, $term) {
                $q->where('title', 'like', $like)
                    ->orWhere('caption', 'like', $like)
                    ->orWhere('artist_name', 'like', $like)
                    ->orWhereHas('tags', fn ($tags) => $tags->where('is_approved', true)->where('name', Tag::canonicalName($term)))
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
            $works->whereHas('tags', fn ($t) => $t->where('is_approved', true)->where('name', $tag));
        }

        foreach ($parsed['exclude_tags'] as $exTag) {
            $works->whereDoesntHave('tags', fn ($t) => $t->where('is_approved', true)->where('name', $exTag));
        }

        return $works;
    }

    /**
     * Live suggestions for the header search box: people, external artists,
     * tags, works, and comics. When term is empty, returns trending tags.
     */
    public function suggest(Request $request)
    {
        $data = $request->validate(['scope' => ['nullable', 'in:gallery,comics,knowledge']]);
        $scope = $data['scope'] ?? 'gallery';
        $rawTerm = trim((string) $request->query('q'));
        $parsed = self::parseOperators($rawTerm);
        $term = $scope === 'gallery'
            ? ($parsed['text'] !== '' ? $parsed['text'] : (! empty($parsed['tags']) ? $parsed['tags'][0] : $rawTerm))
            : $rawTerm;

        if ($scope === 'comics') {
            return response()->json(['comics' => mb_strlen($term) < self::MIN_LENGTH ? [] : Comic::query()
                ->visibleTo($request->user())
                ->visibleRating((bool) $request->user()?->show_explicit)->matchingSearch($term)
                ->orderBy('title')->limit(self::SUGGEST_LIMIT)->get()
                ->map(fn (Comic $comic) => [
                    'label' => $comic->title, 'hint' => 'Comic',
                    'url' => route('comics.show', $comic->slug, false),
                ])]);
        }
        if ($scope === 'knowledge' && mb_strlen($term) < self::MIN_LENGTH) {
            return response()->json(['tags' => [], 'articles' => []]);
        }

        // When term is empty, return trending tags
        if (mb_strlen($term) < self::MIN_LENGTH) {
            $trending = Tag::whereNull('merged_into_id')
                ->where('is_approved', true)
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

        $people = $scope === 'knowledge' ? collect() : User::verified()
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

        $externalArtists = $scope === 'knowledge' ? collect() : Post::whereNotNull('artist_name')
            ->visibleTo($request->user())->visibleRating($showExplicit)
            ->where('artist_name', 'like', $like)
            ->select('artist_name')
            ->distinct()
            ->take(self::SUGGEST_LIMIT)
            ->get()
            ->map(fn ($p) => [
                'label' => $p->artist_name,
                'hint' => 'Artist credit page · not a member profile',
                'is_external' => true,
                'type' => 'external_artist',
                'url' => route('artists.show', ['name' => $p->artist_name], false),
            ]);

        $cleanTag = ltrim($term, '#');
        $tags = Tag::whereNull('merged_into_id')
            ->where('is_approved', true)
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

        if ($scope === 'knowledge') {
            return response()->json([
                'tags' => $tags,
                'articles' => WikiPage::when(! $showExplicit, fn ($q) => $q->where('rating', '!=', 'explicit'))
                    ->matchingSearch($term)->orderBy('title')->limit(self::SUGGEST_LIMIT)->get()
                    ->map(fn (WikiPage $article) => [
                        'label' => $article->title, 'hint' => 'Wiki guide',
                        'url' => route('knowledge.show', ['type' => 'wiki', 'id' => $article->id], false),
                    ]),
            ]);
        }

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

        $comics = Comic::visibleTo($request->user())->where('title', 'like', $like)
            ->when(! $showExplicit, fn ($q) => $q->where('rating', '!=', 'explicit'))
            ->take(self::SUGGEST_LIMIT)
            ->get()
            ->map(fn (Comic $c) => [
                'label' => $c->title,
                'hint' => 'Comic',
                'badge' => 'Comic',
                'type' => 'comic',
                'avatar' => $c->cover()?->thumbUrl() ?: $c->cover()?->url(),
                'url' => route('comics.show', $c->slug, false),
            ]);

        return response()->json([
            'people' => $people,
            'external_artists' => $externalArtists,
            'tags' => $tags,
            'works' => $works,
            'comics' => $comics,
            'trending' => [],
            // the gallery is the results page now
            'more' => route('gallery', ['q' => $rawTerm, 'window' => 'all'], false),
        ]);
    }

    public function trending(Request $request)
    {
        $tags = Tag::whereNull('merged_into_id')
            ->where('is_approved', true)
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
