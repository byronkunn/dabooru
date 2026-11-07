<?php

namespace App\Http\Controllers;

use App\Http\Resources\MediaResource;
use App\Models\ArtistCreditClaim;
use App\Models\Comic;
use App\Models\ComicChapter;
use App\Models\Post;
use App\Models\Tag;
use App\Models\User;
use App\Support\ChapterPublishing;
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
        $works = Post::withoutGlobalScope('published')->where('user_id', $user->id)
            ->with('media.media')->withCount('bookmarks')
            ->orderByDesc('is_draft')->latest()->orderByDesc('id')->simplePaginate(20)->withQueryString();
        $chapters = ComicChapter::whereHas('comic', fn ($query) => $query->where('user_id', $user->id))
            ->whereNull('published_at')->with(['comic:id,title', 'pages.media'])
            ->latest()->orderByDesc('id')->simplePaginate(12, ['*'], 'chapters_page')->withQueryString();
        $claims = ArtistCreditClaim::where('user_id', $user->id)->latest()->limit(20)->get(['id', 'post_id', 'status', 'reason']);
        $claimPosts = Post::visibleTo($user)->visibleRating((bool) $user->show_explicit)
            ->whereIn('id', $claims->pluck('post_id'))->pluck('title', 'id');

        return Inertia::render('Studio/Index', [
            'works' => $works->getCollection()->map(fn (Post $post) => $post->only([
                'id', 'title', 'caption', 'rating', 'type', 'is_draft', 'views', 'bookmarks_count',
            ]) + [
                'tags' => $post->is_draft ? implode(', ', $post->draft_tags ?? []) : '',
                'media' => $post->is_draft || $post->type === 'album'
                    ? $post->media->map(fn ($item) => $this->mediaItem($item)) : [],
            ]),
            'chapters' => $chapters->getCollection()->map(fn (ComicChapter $chapter) => $chapter->only(['id', 'title', 'rating', 'number', 'views']) + [
                'series' => $chapter->comic->title,
                'tags' => implode(', ', $chapter->draft_tags ?? []),
                'media' => $chapter->pages->map(fn ($item) => $this->mediaItem($item)),
            ]),
            'claims' => $claims->map(fn ($claim) => $claim->only(['id', 'status', 'reason']) + [
                'title' => $claimPosts->get($claim->post_id),
                'url' => $claimPosts->has($claim->post_id) ? route('posts.show', $claim->post_id, false) : null,
            ]),
            'worksPages' => ['nextPage' => $works->nextPageUrl(), 'previousPage' => $works->previousPageUrl()],
            'chapterPages' => ['nextPage' => $chapters->nextPageUrl(), 'previousPage' => $chapters->previousPageUrl()],
        ]);
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
            'tags' => ['present', 'nullable', 'string', 'max:500'],
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
