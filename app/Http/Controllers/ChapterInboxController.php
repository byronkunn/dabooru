<?php

namespace App\Http\Controllers;

use App\Models\Block;
use App\Models\ComicChapter;
use App\Models\ComicFollow;
use App\Models\ReadingProgress;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Inertia\Inertia;

class ChapterInboxController extends Controller
{
    private function followedChapters(Request $request)
    {
        $viewer = $request->user();

        return ComicChapter::published()->visibleAfterModeration()
            ->when(! $viewer->show_explicit, fn ($query) => $query->where('rating', '!=', 'explicit'))
            ->whereIn('comic_id', ComicFollow::where('user_id', $viewer->id)->select('comic_id'))
            ->whereHas('comic', fn ($query) => $query->visibleRating((bool) $viewer->show_explicit)
                ->whereNotIn('user_id', Block::counterpartIds($viewer->id)));
    }

    public function index(Request $request)
    {
        $viewer = $request->user();
        $chapters = $this->followedChapters($request)
            ->whereNotExists(fn ($query) => $query->from('comic_chapter_reads')->where('user_id', $viewer->id)
                ->whereColumn('comic_chapter_id', 'comic_chapters.id'))
            ->whereNotExists(fn ($query) => $query->from('reading_progress')->where('user_id', $viewer->id)
                ->whereColumn('comic_chapter_id', 'comic_chapters.id')->whereRaw('page >= (SELECT COUNT(*) FROM comic_pages WHERE comic_pages.comic_chapter_id = comic_chapters.id)'))
            ->with('comic:id,slug,title')
            ->latest('published_at')->orderByDesc('id')->simplePaginate(25);
        $progress = ReadingProgress::where('user_id', $viewer->id)
            ->whereIn('comic_id', $chapters->getCollection()->pluck('comic_id'))
            ->with(['chapter' => fn ($query) => $query->withCount('pages')])->get()->keyBy('comic_id');
        $resumeIds = $this->followedChapters($request)->whereKey($progress->pluck('comic_chapter_id')->all())
            ->pluck('id')->flip();

        return Inertia::render('Discovery/ChapterInbox', [
            'chapters' => $chapters->getCollection()->map(function (ComicChapter $chapter) use ($progress, $resumeIds) {
                $reading = $progress->get($chapter->comic_id);
                $resume = $reading?->chapter && $reading->chapter->number < $chapter->number
                    && $reading->page < $reading->chapter->pages_count && $resumeIds->has($reading->comic_chapter_id);

                return [
                    'id' => $chapter->id, 'series' => $chapter->comic->title, 'number' => $chapter->number,
                    'title' => $chapter->title, 'published' => $chapter->published_at->diffForHumans(),
                    'url' => route('comics.read', [$chapter->comic->slug, $chapter->number], false),
                    'resume' => $resume ? route('comics.read', [$chapter->comic->slug, $reading->chapter->number], false) : null,
                ];
            }),
            'nextPage' => $chapters->nextPageUrl(),
            'previousPage' => $chapters->previousPageUrl(),
        ]);
    }

    public function markRead(Request $request, ComicChapter $chapter)
    {
        abort_unless($this->followedChapters($request)->whereKey($chapter->id)->exists(), 404);
        DB::table('comic_chapter_reads')->insertOrIgnore([
            'user_id' => $request->user()->id, 'comic_chapter_id' => $chapter->id,
        ]);

        return back();
    }
}
