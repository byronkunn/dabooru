<?php

namespace App\Http\Controllers;

use App\Http\Resources\PostResource;
use App\Models\Post;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class ArtistController extends Controller
{
    private function works(Request $request): Builder
    {
        return Post::visibleTo($request->user())
            ->visibleRating((bool) $request->user()?->show_explicit)
            ->whereNotNull('artist_name')->where('artist_name', '!=', '');
    }

    public function index(Request $request): Response
    {
        $data = $request->validate(['q' => ['nullable', 'string', 'max:120']]);
        $artists = $this->works($request)
            ->when(! empty($data['q']), fn ($query) => $query->whereLike('artist_name', '%'.$data['q'].'%'))
            ->selectRaw('artist_name, COUNT(*) AS works_count')
            ->groupBy('artist_name')->orderBy('artist_name')
            ->simplePaginate(30)->withQueryString();

        $popularArtists = $artists->isEmpty() && ! empty($data['q'])
            ? $this->works($request)->selectRaw('artist_name, COUNT(*) AS works_count')
                ->groupBy('artist_name')->orderByDesc('works_count')->orderBy('artist_name')->limit(4)->get()
                ->map(fn ($artist) => ['name' => $artist->artist_name, 'works' => (int) $artist->works_count,
                    'url' => route('artists.show', ['name' => $artist->artist_name], false)])
            : collect();

        return Inertia::render('Artists/Index', [
            'search' => $data['q'] ?? '',
            'artists' => $artists->getCollection()->map(fn ($artist) => [
                'name' => $artist->artist_name,
                'works' => (int) $artist->works_count,
                'url' => route('artists.show', ['name' => $artist->artist_name], false),
            ]),
            'nextPage' => $artists->nextPageUrl(),
            'previousPage' => $artists->previousPageUrl(),
            'popularArtists' => $popularArtists,
        ]);
    }

    public function show(Request $request): Response
    {
        $data = $request->validate(['name' => ['required', 'string', 'max:120']]);
        $query = $this->works($request)->where('artist_name', $data['name']);
        abort_unless((clone $query)->exists(), 404);
        $total = (clone $query)->count();
        $posts = $query->with(['user', 'media.media', 'chapter.comic', 'creditedArtist'])
            ->latest('id')->simplePaginate(24)->withQueryString();

        return Inertia::render('Artists/Show', [
            'artist' => ['name' => $data['name'], 'works' => $total],
            'posts' => PostResource::collection($posts->getCollection())->resolve(),
            'nextPage' => $posts->nextPageUrl(),
            'previousPage' => $posts->previousPageUrl(),
        ]);
    }
}
