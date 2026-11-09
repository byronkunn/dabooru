<?php

namespace App\Http\Controllers;

use App\Http\Resources\PostResource;
use App\Models\Post;
use App\Models\SavedSearch;
use App\Support\CollectionLibrary;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;
use Inertia\Inertia;

class SavedSearchController extends Controller
{
    public function index(Request $request, ?SavedSearch $savedSearch = null)
    {
        $user = $request->user();
        $searches = SavedSearch::where('user_id', $user->id)->orderBy('name')->get(['id', 'name', 'filters']);
        $muted = CollectionLibrary::mutedTags($request);
        $posts = null;
        $searchUrl = null;

        if ($savedSearch) {
            abort_unless($savedSearch->user_id === $user->id, 404);
            $filters = $savedSearch->filters;
            if (isset($filters['gallery'])) {
                [$gallery, $parsed] = GalleryController::filters($filters['gallery'], $user);
                $gallery['explicit'] = $gallery['explicit'] && $user->show_explicit;
                $query = GalleryController::workQuery($user, $gallery, $parsed);
                $searchUrl = route('gallery', $gallery);
            } else {
                $parsed = SearchController::parseOperators($filters['q'] ?? '');
                $query = SearchController::workQuery($user, $parsed, $filters['artist'] ?? $parsed['artist'], $filters['type'] ?? $parsed['type'])
                    ->latest('created_at')->orderByDesc('id');
                $searchUrl = route('gallery', $filters + ['window' => 'all']);
            }
            $posts = $query->excludingTags($muted)->with(['user', 'media.media', 'chapter.comic'])->simplePaginate(30);
        }

        return Inertia::render('Discovery/SavedSearches', [
            'searches' => $searches,
            'selected' => $savedSearch?->only(['id', 'name', 'filters']),
            'searchUrl' => $searchUrl,
            'mutedTags' => $muted,
            'posts' => $posts ? PostResource::collection($posts->items())->resolve() : [],
            'nextPage' => $posts?->nextPageUrl(),
            'previousPage' => $posts?->previousPageUrl(),
        ]);
    }

    public function store(Request $request)
    {
        $data = $request->validate([
            'name' => ['required', 'string', 'max:80'],
            'q' => ['nullable', 'string', 'max:500'],
            'artist' => ['nullable', 'string', 'max:120'],
            'type' => ['nullable', Rule::in(Post::TYPES)],
            'gallery' => ['sometimes', 'array:q,tag,tags,exclude_tags,format,window,sort,explicit,min_duration,max_duration,min_likes,min_views,min_stars'],
            'gallery.q' => ['nullable', 'string', 'max:500'],
            'gallery.tags' => ['sometimes', 'array', 'max:8'],
            'gallery.tags.*' => ['string', 'max:40'],
            'gallery.exclude_tags' => ['sometimes', 'array', 'max:8'],
            'gallery.exclude_tags.*' => ['string', 'max:40'],
        ]);
        $user = $request->user();
        if (isset($data['gallery'])) {
            [$gallery] = GalleryController::filters($data['gallery'], $user);
            $filters = ['q' => $gallery['q'], 'gallery' => $gallery];
        } else {
            $parsed = SearchController::parseOperators($data['q'] ?? '');
            if (mb_strlen($parsed['text']) < SearchController::MIN_LENGTH
                && ! ($data['artist'] ?? $parsed['artist']) && ! ($data['type'] ?? $parsed['type'])
                && ! $parsed['rating'] && ! $parsed['tags'] && ! $parsed['exclude_tags']) {
                throw ValidationException::withMessages(['q' => 'Enter a search or choose a filter.']);
            }
            $filters = array_intersect_key($data, array_flip(['q', 'artist', 'type']));
        }

        $search = SavedSearch::updateOrCreate(
            ['user_id' => $user->id, 'name' => $data['name']],
            ['filters' => $filters],
        );

        return redirect()->route('saved-searches.show', $search);
    }

    public function destroy(Request $request, SavedSearch $savedSearch)
    {
        abort_unless($savedSearch->user_id === $request->user()->id, 404);
        $savedSearch->delete();

        return redirect()->route('saved-searches.index');
    }
}
