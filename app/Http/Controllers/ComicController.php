<?php

namespace App\Http\Controllers;

use App\Http\Resources\MediaResource;
use App\Models\Comic;
use App\Models\ComicChapter;
use App\Models\Post;
use App\Models\ReadingProgress;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class ComicController extends Controller
{
    public function index(Request $request): Response
    {
        $comics = Comic::query()
            ->with(['user', 'chapters' => fn ($q) => $q->with('pages.media')])
            ->visibleRating($request->user()?->show_explicit ?? false)
            ->latest('last_chapter_at')
            ->take(24)
            ->get()
            ->map(fn (Comic $c) => $this->seriesCard($c));

        return Inertia::render('Comics/Index', ['comics' => $comics]);
    }

    public function show(Request $request, string $slug): Response
    {
        $comic = Comic::with(['user', 'chapters.pages.media'])
            ->where('slug', $slug)
            ->firstOrFail();

        $viewer = $request->user();
        $latest = $comic->chapters()->first();
        $progress = $viewer
            ? ReadingProgress::where('user_id', $viewer->id)->where('comic_id', $comic->id)->first()
            : null;

        return Inertia::render('Comics/Show', [
            'comic' => [
                'id' => $comic->id,
                'slug' => $comic->slug,
                'title' => $comic->title,
                'description' => $comic->description,
                'rating' => $comic->rating,
                'rating_label' => \App\Support\Rating::label($comic->rating),
                'status' => $comic->status,
                'views' => $comic->views,
                'author' => (new \App\Http\Resources\UserResource($comic->user))->resolve(),
                'cover' => $comic->cover()?->url(),
                'chapters' => $comic->chapters->map(fn (ComicChapter $ch) => [
                    'id' => $ch->id,
                    'number' => $ch->number,
                    'title' => $ch->title,
                    'pages' => $ch->pages->count(),
                    'rating' => $ch->rating,
                    'published' => $ch->published_at?->diffForHumans(short: true),
                    'url' => route('comics.read', [$comic->slug, $ch->number], false),
                    'read' => $progress && $progress->comic_chapter_id === $ch->id,
                ]),
                'continue' => $latest ? [
                    'chapter' => $latest->number,
                    'url' => route('comics.read', [$comic->slug, $latest->number], false),
                ] : null,
                'following' => $viewer ? $viewer->following()->whereKey($comic->user_id)->exists() : false,
                'followedSeries' => $viewer ? $comic->follows()->where('user_id', $viewer->id)->exists() : false,
                'progress' => $progress ? ['chapter' => $progress->comic_chapter_id, 'page' => $progress->page] : null,
            ],
        ]);
    }

    public function read(Request $request, string $slug, int $number): Response
    {
        $comic = Comic::where('slug', $slug)->firstOrFail();
        $chapter = ComicChapter::with('pages.media')
            ->where('comic_id', $comic->id)
            ->where('number', $number)
            ->firstOrFail();

        $chapter->increment('views');

        $viewer = $request->user();
        $progress = $viewer
            ? ReadingProgress::where('user_id', $viewer->id)->where('comic_id', $comic->id)->first()
            : null;

        return Inertia::render('Comics/Read', [
            'comic' => [
                'slug' => $comic->slug,
                'title' => $comic->title,
                'author' => $comic->user->name,
                'rating' => $chapter->rating,
            ],
            'chapter' => [
                'id' => $chapter->id,
                'number' => $chapter->number,
                'title' => $chapter->title,
                'next' => ($next = $chapter->next()) ? [
                    'number' => $next->number,
                    'url' => route('comics.read', [$comic->slug, $next->number], false),
                ] : null,
                'previous' => ($prev = $chapter->previous()) ? [
                    'number' => $prev->number,
                    'url' => route('comics.read', [$comic->slug, $prev->number], false),
                ] : null,
            ],
            'pages' => $chapter->pages->map(fn ($page) => (new MediaResource($page->media))->resolve()),
            'startPage' => $progress && $progress->comic_chapter_id === $chapter->id ? (int) $progress->page : 1,
            'defaultMode' => 'page',
        ]);
    }

    public function follow(Request $request, Comic $comic): \Illuminate\Http\RedirectResponse
    {
        $existing = $comic->follows()->where('user_id', $request->user()->id)->delete();

        if (! $existing) {
            $comic->follows()->create(['user_id' => $request->user()->id]);
        }

        return back();
    }

    public function progress(Request $request, Comic $comic): \Illuminate\Http\JsonResponse
    {
        if (! $request->user()) {
            return response()->json(['saved' => false]);
        }

        $data = $request->validate([
            'chapter_id' => ['required', 'integer', 'exists:comic_chapters,id'],
            'page' => ['required', 'integer', 'min:1'],
        ]);

        ReadingProgress::updateOrCreate(
            ['user_id' => $request->user()->id, 'comic_id' => $comic->id],
            ['comic_chapter_id' => $data['chapter_id'], 'page' => $data['page']]
        );

        return response()->json(['saved' => true]);
    }

    private function seriesCard(Comic $c): array
    {
        return [
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
        ];
    }
}
