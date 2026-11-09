<?php

namespace App\Http\Controllers;

use App\Http\Resources\MediaResource;
use App\Models\ArtistCreditClaim;
use App\Models\Comic;
use App\Models\ComicChapter;
use App\Models\Post;
use App\Models\StudioPlan;
use App\Models\Tag;
use App\Models\User;
use App\Support\ChapterPublishing;
use App\Support\StudioAnalytics;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;
use Inertia\Inertia;

class StudioController extends Controller
{
    public function index(Request $request)
    {
        $user = $request->user();
        $filters = $request->validate([
            'tab' => ['sometimes', Rule::in(['overview', 'posts', 'analytics', 'planner', 'comics'])],
            'days' => ['sometimes', Rule::in([7, 30, 90])],
            'ownership' => ['sometimes', Rule::in(['all', 'original', 'shared'])],
            'sort' => ['sometimes', Rule::in(['newest', 'views', 'likes', 'saves', 'engagement', 'growth', 'ratings'])],
            'post' => ['nullable', 'integer', 'min:1'],
            'stage' => ['nullable', Rule::in(StudioPlan::STAGES)],
        ]);
        $tab = $filters['tab'] ?? 'overview';
        $days = (int) ($filters['days'] ?? 30);
        $ownership = $filters['ownership'] ?? 'all';
        $sort = $filters['sort'] ?? 'engagement';
        $start = now()->startOfDay()->subDays($days - 1);
        $props = [
            'studioFilters' => ['tab' => $tab, 'days' => $days, 'ownership' => $ownership, 'sort' => $sort, 'post' => $filters['post'] ?? null],
        ];

        // Each tab only pays for the queries it renders; the page defaults the rest to empty.
        if (in_array($tab, ['overview', 'analytics'], true)) {
            $props['analytics'] = StudioAnalytics::dashboard($user, $days, $ownership, isset($filters['post']) ? (int) $filters['post'] : null);
        }
        if ($tab === 'analytics') {
            $props['performance'] = StudioAnalytics::ranked(StudioAnalytics::works($user, $ownership), $start, now(), $sort)
                ->limit(6)->get()->map(StudioAnalytics::card(...));
        }
        if ($tab === 'posts') {
            $performance = StudioAnalytics::ranked(StudioAnalytics::works($user, $ownership), $start, now(), $sort)
                ->simplePaginate(20, ['*'], 'performance_page')->withQueryString();
            $works = Post::withoutGlobalScope('published')->where('user_id', $user->id)
                ->with('media.media')->withCount('bookmarks')
                ->orderByDesc('is_draft')->latest()->orderByDesc('id')->simplePaginate(20)->withQueryString();
            $props['performance'] = $performance->getCollection()->map(StudioAnalytics::card(...));
            $props['performancePages'] = ['nextPage' => $performance->nextPageUrl(), 'previousPage' => $performance->previousPageUrl()];
            $props['works'] = $works->getCollection()->map(fn (Post $post) => $post->only([
                'id', 'title', 'caption', 'rating', 'type', 'is_draft', 'views', 'bookmarks_count',
            ]) + [
                'tags' => $post->is_draft ? implode(', ', $post->draft_tags ?? []) : '',
                'media' => $post->is_draft || $post->type === 'album'
                    ? $post->media->map(fn ($item) => $this->mediaItem($item)) : [],
            ]);
            $props['worksPages'] = ['nextPage' => $works->nextPageUrl(), 'previousPage' => $works->previousPageUrl()];

            // series and chapters the artist can move works into, for the bulk tool
            $props['comics'] = Comic::where('user_id', $user->id)
                ->with(['chapters' => fn ($q) => $q->orderBy('number')->select('id', 'comic_id', 'title', 'number')])
                ->orderBy('title')
                ->get(['id', 'title'])
                ->map(fn (Comic $comic) => [
                    'id' => $comic->id,
                    'title' => $comic->title,
                    'chapters' => $comic->chapters->map(fn ($c) => ['id' => $c->id, 'label' => 'Ch. '.$c->number.($c->title ? ' · '.$c->title : '')])->all(),
                ])
                ->all();
        }
        if ($tab === 'comics') {
            $comics = StudioAnalytics::comics($user);
            $chapters = ComicChapter::whereHas('comic', fn ($query) => $query->where('user_id', $user->id))
                ->whereNull('published_at')->with(['comic:id,title', 'pages.media'])
                ->latest()->orderByDesc('id')->simplePaginate(12, ['*'], 'chapters_page')->withQueryString();
            $props['comicAnalytics'] = $comics['series'];
            $props['comicPages'] = ['nextPage' => $comics['nextPage'], 'previousPage' => $comics['previousPage']];
            $props['comicTrackingSince'] = $comics['trackingSince'];
            $props['chapters'] = $chapters->getCollection()->map(fn (ComicChapter $chapter) => $chapter->only(['id', 'title', 'rating', 'number', 'views']) + [
                'series' => $chapter->comic->title,
                'tags' => implode(', ', $chapter->draft_tags ?? []),
                'media' => $chapter->pages->map(fn ($item) => $this->mediaItem($item)),
            ]);
            $props['chapterPages'] = ['nextPage' => $chapters->nextPageUrl(), 'previousPage' => $chapters->previousPageUrl()];
        }
        if ($tab === 'planner') {
            $props += StudioPlanController::plannerData($user, $filters['stage'] ?? null);
        }
        if ($tab === 'overview') {
            $claims = ArtistCreditClaim::where('user_id', $user->id)->latest()->limit(20)->get(['id', 'post_id', 'status', 'reason']);
            $claimPosts = Post::visibleTo($user)->visibleRating((bool) $user->show_explicit)
                ->whereIn('id', $claims->pluck('post_id'))->pluck('title', 'id');
            $props['claims'] = $claims->map(fn ($claim) => $claim->only(['id', 'status', 'reason']) + [
                'title' => $claimPosts->get($claim->post_id),
                'url' => $claimPosts->has($claim->post_id) ? route('posts.show', $claim->post_id, false) : null,
            ]);
        }

        return Inertia::render('Studio/Index', $props);
    }

    /**
     * Preview or apply a bulk change to several of the artist's own works.
     *
     * One endpoint for both, so the preview always describes exactly what the
     * apply would do — the artist sees the same list the change will touch.
     */
    public function bulk(Request $request)
    {
        $data = $request->validate([
            'works' => ['required', 'array', 'min:1', 'max:100'],
            'works.*' => ['integer', 'min:1'],
            'action' => ['required', Rule::in(['add_tags', 'remove_tags', 'rating', 'series'])],
            'tags' => ['nullable', 'string', 'max:400'],
            'rating' => ['nullable', Rule::in(\App\Support\Rating::ALL)],
            'comic_id' => ['nullable', 'integer', 'exists:comics,id'],
            'chapter_id' => ['nullable', 'integer', 'exists:comic_chapters,id'],
            'preview' => ['sometimes', 'boolean'],
        ]);

        $user = $request->user();

        // only the artist's own works can be touched in bulk
        $works = Post::withoutGlobalScope('published')
            ->where('user_id', $user->id)
            ->whereIn('id', $data['works'])
            ->with(['media.media', 'chapter.comic'])
            ->orderBy('id')
            ->get();

        abort_if($works->isEmpty(), 422, 'None of those works belong to you.');

        // work out the concrete effect of the action before writing anything
        $tagNames = \App\Models\Tag::parse($data['tags'] ?? '');

        if ($data['action'] === 'series') {
            abort_unless(! empty($data['comic_id']) && ! empty($data['chapter_id']), 422, 'Choose a series and a chapter.');
            $chapter = ComicChapter::whereKey($data['chapter_id'])->whereHas('comic', fn ($q) => $q->where('user_id', $user->id))->first();
            abort_unless($chapter && (int) $chapter->comic_id === (int) $data['comic_id'], 422, 'That chapter is not in your series.');
        }

        $preview = $works->map(function (Post $post) use ($data, $tagNames) {
            $changes = [];

            if ($data['action'] === 'add_tags') {
                $current = $post->tags()->pluck('name')->all();
                $adding = array_values(array_diff($tagNames, $current));
                if ($adding !== []) {
                    $changes[] = '+'.implode(', +', $adding);
                }
            }

            if ($data['action'] === 'remove_tags') {
                $current = $post->tags()->pluck('name')->all();
                $removing = array_values(array_intersect($tagNames, $current));
                if ($removing !== []) {
                    $changes[] = '−'.implode(', −', $removing);
                }
            }

            if ($data['action'] === 'rating' && $post->rating !== $data['rating']) {
                $changes[] = $post->rating.' → '.$data['rating'];
            }

            if ($data['action'] === 'series') {
                $changes[] = 'move to series';
            }

            return [
                'id' => $post->id,
                'title' => $post->title ?: 'Untitled '.$post->type,
                'thumb' => $post->media->first()?->media?->thumbUrl() ?? $post->media->first()?->media?->url(),
                'rating' => $post->rating,
                'changes' => $changes,
                'affected' => $changes !== [],
            ];
        })->values();

        if ($request->boolean('preview')) {
            // returned as JSON: the page keeps its selection instead of remounting
            return response()->json([
                'action' => $data['action'],
                'items' => $preview->all(),
                'affected' => $preview->where('affected', true)->count(),
            ]);
        }

        $applied = DB::transaction(function () use ($works, $data, $tagNames, $user) {
            $count = 0;
            foreach ($works as $post) {
                if ($data['action'] === 'add_tags') {
                    foreach ($tagNames as $name) {
                        $post->tags()->syncWithoutDetaching([\App\Models\Tag::firstOrCreate(['name' => $name])->id]);
                    }
                    $post->forceFill(['tag_version' => ($post->tag_version ?? 1) + 1])->save();
                    $count++;
                } elseif ($data['action'] === 'remove_tags') {
                    $post->tags()->detach(\App\Models\Tag::whereIn('name', $tagNames)->pluck('id'));
                    $post->forceFill(['tag_version' => ($post->tag_version ?? 1) + 1])->save();
                    $count++;
                } elseif ($data['action'] === 'rating') {
                    $post->update(['rating' => $data['rating']]);
                    if ($post->chapter) {
                        $post->chapter()->update(['rating' => $data['rating']]);
                    }
                    $count++;
                } elseif ($data['action'] === 'series') {
                    $post->forceFill(['comic_chapter_id' => $data['chapter_id'], 'type' => 'comic'])->save();
                    $count++;
                }
            }

            return $count;
        });

        return back()->with('success', "Updated {$applied} work".($applied === 1 ? '' : 's').'.');
    }

    public function updateWork(Request $request, int $work)
    {
        $data = $this->validateEditor($request, 20, true);
        DB::transaction(function () use ($request, $work, $data) {
            $post = Post::withoutGlobalScope('published')->where('user_id', $request->user()->id)->lockForUpdate()->findOrFail($work);
            abort_unless($post->is_draft || $post->type === 'album', 422);
            if ($post->type === 'album') {
                $this->reorder($post->media()->get(), $data['order'], 0);
            }

            if ($post->is_draft) {
                $tags = Tag::parse($data['tags']);
                $post->update(['title' => $data['title'], 'caption' => $data['caption'], 'rating' => $data['rating'], 'draft_tags' => $tags]);
                if ($data['publish']) {
                    $post->forceFill(['is_draft' => false, 'draft_tags' => null, 'created_at' => now()])->save();
                    Tag::syncFor($post, $tags);
                }
            }
        });

        return back()->with('success', $data['publish'] ? 'Work published.' : 'Changes saved.');
    }

    public function updateChapter(Request $request, int $chapter)
    {
        $data = $this->validateEditor($request, 60, false);
        DB::transaction(function () use ($request, $chapter, $data) {
            $draft = ComicChapter::whereHas('comic', fn ($query) => $query->where('user_id', $request->user()->id))
                ->whereNull('published_at')->lockForUpdate()->findOrFail($chapter);
            $comic = Comic::lockForUpdate()->findOrFail($draft->comic_id);
            $this->reorder($draft->pages()->get(), $data['order'], 1);
            $tags = Tag::parse($data['tags']);
            $draft->update(['title' => $data['title'], 'rating' => $data['rating'], 'draft_tags' => $tags]);
            if ($data['publish']) {
                ChapterPublishing::publish($comic, $draft, $tags);
            }
        });

        return back()->with('success', $data['publish'] ? 'Chapter published.' : 'Changes saved.');
    }

    public function discardWork(Request $request, int $work)
    {
        DB::transaction(function () use ($request, $work) {
            Post::withoutGlobalScope('published')->where('user_id', $request->user()->id)
                ->where('is_draft', true)->lockForUpdate()->findOrFail($work)->delete();
        });

        return back()->with('success', 'Draft discarded.');
    }

    public function discardChapter(Request $request, int $chapter)
    {
        DB::transaction(function () use ($request, $chapter) {
            ComicChapter::whereHas('comic', fn ($query) => $query->where('user_id', $request->user()->id))
                ->whereNull('published_at')->lockForUpdate()->findOrFail($chapter)->delete();
        });

        return back()->with('success', 'Chapter draft discarded.');
    }

    private function validateEditor(Request $request, int $maxItems, bool $work): array
    {
        $rules = [
            'title' => ['present', 'nullable', 'string', 'max:160'],
            'rating' => ['required', Rule::in(User::RATINGS)],
            'tags' => ['present', 'nullable', 'string', 'max:2050'],
            'order' => ['required', 'array', 'list', 'max:'.$maxItems],
            'order.*' => ['required', 'integer', 'distinct'],
            'publish' => ['required', 'boolean'],
        ];
        if ($work) {
            $rules['caption'] = ['present', 'nullable', 'string', 'max:1000'];
        }

        return $request->validate($rules);
    }

    private function reorder($items, array $order, int $first): void
    {
        $ids = array_map('intval', $order);
        $byId = $items->keyBy('id');
        if (count($ids) !== $items->count() || array_diff($ids, $byId->keys()->all())) {
            throw ValidationException::withMessages(['order' => 'The item list changed. Reload Studio and try again.']);
        }

        // Move to temporary positions first so chapter positions stay unique.
        $offset = (int) $items->max('position') + 1;
        foreach ($ids as $index => $id) {
            $byId[$id]->update(['position' => $offset + $index]);
        }
        foreach ($ids as $index => $id) {
            $byId[$id]->update(['position' => $first + $index]);
        }
    }

    private function mediaItem($item): array
    {
        return ['id' => $item->id, 'preview' => $item->media ? (new MediaResource($item->media))->resolve() : null];
    }
}
