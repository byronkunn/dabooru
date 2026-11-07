<?php

namespace App\Http\Controllers;

use App\Http\Resources\PostResource;
use App\Models\Post;
use App\Models\SavedSearch;
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
        $posts = null;

        if ($savedSearch) {
            abort_unless($savedSearch->user_id === $user->id, 404);
            $filters = $savedSearch->filters;
            $parsed = SearchController::parseOperators($filters['q'] ?? '');
            $posts = SearchController::workQuery($user, $parsed, $filters['artist'] ?? $parsed['artist'], $filters['type'] ?? $parsed['type'])
                ->with(['user', 'media.media', 'chapter.comic'])->latest('created_at')->orderByDesc('id')->simplePaginate(30);
        }

        return Inertia::render('Discovery/SavedSearches', [
            'searches' => $searches,
            'selected' => $savedSearch?->only(['id', 'name', 'filters']),
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
        ]);
        $parsed = SearchController::parseOperators($data['q'] ?? '');
        if (mb_strlen($parsed['text']) < SearchController::MIN_LENGTH
            && ! ($data['artist'] ?? $parsed['artist']) && ! ($data['type'] ?? $parsed['type'])
            && ! $parsed['rating'] && ! $parsed['tags'] && ! $parsed['exclude_tags']) {
            throw ValidationException::withMessages(['q' => 'Enter a search or choose a filter.']);
        }

        $search = SavedSearch::updateOrCreate(
            ['user_id' => $request->user()->id, 'name' => $data['name']],
            ['filters' => array_intersect_key($data, array_flip(['q', 'artist', 'type']))],
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
