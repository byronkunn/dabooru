<?php

namespace App\Http\Controllers;

use App\Models\ComicChapter;
use App\Models\Post;
use App\Models\StudioPlan;
use App\Models\User;
use Carbon\CarbonImmutable;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;

class StudioPlanController extends Controller
{
    public static function plannerData(User $user, ?string $stage = null): array
    {
        abort_if($user->isBanned(), 403);
        $owned = StudioPlan::where('user_id', $user->id);
        $plans = (clone $owned)->when($stage, fn ($q) => $q->where('stage', $stage))->latest('id')
            ->simplePaginate(20, [
                'id', 'title', 'notes', 'stage', 'deadline_at', 'publish_at', 'timezone',
                'post_id', 'comic_chapter_id', 'delivery_status', 'delivery_error', 'published_at',
            ], 'plans_page')->withQueryString();
        // Counts cover every page so the board headers stay accurate while paginating.
        $counts = (clone $owned)->selectRaw('stage, COUNT(*) as total')->groupBy('stage')->pluck('total', 'stage');

        return [
            'plans' => $plans->getCollection(),
            'planStage' => $stage,
            'planCounts' => collect(StudioPlan::STAGES)->mapWithKeys(fn ($name) => [$name => (int) $counts->get($name, 0)]),
            'planPages' => ['nextPage' => $plans->nextPageUrl(), 'previousPage' => $plans->previousPageUrl()],
            'draftWorks' => Post::withoutGlobalScope('published')->where('user_id', $user->id)
                ->where('is_draft', true)->whereNull('moderation_hidden_at')->latest('id')->get(['id', 'title']),
            'draftChapters' => ComicChapter::whereHas('comic', fn ($q) => $q->where('user_id', $user->id))
                ->whereNull('published_at')->with('comic:id,title')->latest('id')->get()
                ->map(fn ($chapter) => $chapter->only(['id', 'title', 'number']) + ['series' => $chapter->comic->title]),
        ];
    }

    public function store(Request $request): RedirectResponse
    {
        $user = $this->owner($request);
        DB::transaction(function () use ($request, $user) {
            abort_if(User::lockForUpdate()->findOrFail($user->id)->isBanned(), 403);
            $data = $this->validated($request, $user);
            $plan = new StudioPlan($data);
            $plan->user_id = $user->id;
            $this->deliveryState($plan);
            $plan->save();
        });

        return back()->with('success', 'Private plan created.');
    }

    public function update(Request $request, int $plan): RedirectResponse
    {
        $user = $this->owner($request);
        DB::transaction(function () use ($request, $user, $plan) {
            $entry = StudioPlan::where('user_id', $user->id)->lockForUpdate()->findOrFail($plan);
            abort_if(User::lockForUpdate()->findOrFail($user->id)->isBanned(), 403);
            $data = $this->validated($request, $user, $entry);
            $entry->fill($data);
            $this->deliveryState($entry);
            $entry->save();
        });

        return back()->with('success', 'Private plan saved.');
    }

    public function destroy(Request $request, int $plan): RedirectResponse
    {
        $user = $this->owner($request);
        DB::transaction(fn () => StudioPlan::where('user_id', $user->id)->lockForUpdate()->findOrFail($plan)->delete());

        return back()->with('success', 'Plan deleted. Linked work and uploads were kept.');
    }

    private function owner(Request $request): User
    {
        abort_unless($request->user(), 401);
        abort_if($request->user()->isBanned(), 403);

        return $request->user();
    }

    private function validated(Request $request, User $user, ?StudioPlan $plan = null): array
    {
        $data = $request->validate([
            'title' => ['required', 'string', 'max:160'],
            'notes' => ['nullable', 'string', 'max:10000'],
            'stage' => ['required', Rule::in(StudioPlan::STAGES)],
            'timezone' => ['required', 'string', 'max:64', 'timezone:all'],
            'deadline_at' => ['nullable', 'date', 'regex:/^\d{4}-\d{2}-\d{2}T\d{2}:\d{2}(:\d{2}(\.\d{1,6})?)?(Z|[+-]\d{2}:\d{2})$/'],
            'publish_at' => ['nullable', 'date', 'regex:/^\d{4}-\d{2}-\d{2}T\d{2}:\d{2}(:\d{2}(\.\d{1,6})?)?(Z|[+-]\d{2}:\d{2})$/'],
            'post_id' => ['nullable', 'integer'],
            'comic_chapter_id' => ['nullable', 'integer'],
        ]);
        foreach (['notes', 'deadline_at', 'publish_at', 'post_id', 'comic_chapter_id'] as $field) {
            $data[$field] ??= null;
        }
        foreach (['deadline_at', 'publish_at'] as $field) {
            if ($data[$field]) {
                $data[$field] = CarbonImmutable::parse($data[$field])->utc();
            }
        }
        if ($data['post_id'] && $data['comic_chapter_id']) {
            throw ValidationException::withMessages(['post_id' => 'Link one draft: a work or a comic chapter, not both.']);
        }
        if ($plan?->delivery_status === 'published'
            && ((int) $plan->post_id !== (int) $data['post_id'] || (int) $plan->comic_chapter_id !== (int) $data['comic_chapter_id'])) {
            throw ValidationException::withMessages(['post_id' => 'A completed publication keeps its original link. Create a new plan for another draft.']);
        }
        $destinationPublished = false;
        if ($data['post_id']) {
            $post = Post::withoutGlobalScope('published')->where('user_id', $user->id)
                ->lockForUpdate()->find($data['post_id']);
            $completedLink = $plan?->delivery_status === 'published' && $plan->post_id === (int) $data['post_id'];
            if (! $post || $post->moderation_hidden_at || (! $post->is_draft && ! $completedLink && $data['stage'] !== 'published')) {
                throw ValidationException::withMessages(['post_id' => 'Choose an available draft that you own.']);
            }
            $destinationPublished = ! $post->is_draft;
        }
        if ($data['comic_chapter_id']) {
            $chapter = ComicChapter::whereHas('comic', fn ($q) => $q->where('user_id', $user->id))
                ->lockForUpdate()->find($data['comic_chapter_id']);
            $completedLink = $plan?->delivery_status === 'published' && $plan->comic_chapter_id === (int) $data['comic_chapter_id'];
            $moderated = $chapter && Post::withoutGlobalScope('published')->withTrashed()
                ->where('comic_chapter_id', $chapter->id)->whereNotNull('moderation_hidden_at')->exists();
            if (! $chapter || $moderated || ($chapter->published_at && ! $completedLink && $data['stage'] !== 'published')) {
                throw ValidationException::withMessages(['comic_chapter_id' => 'Choose an unpublished chapter that you own.']);
            }
            $destinationPublished = $chapter->published_at && $chapter->published_at->isPast();
        }
        if ($data['stage'] === 'scheduled') {
            if ($destinationPublished) {
                throw ValidationException::withMessages(['stage' => 'Only an unpublished draft can be scheduled.']);
            }
            if (! $data['publish_at'] || $data['publish_at']->isPast()) {
                throw ValidationException::withMessages(['publish_at' => 'Scheduling requires a future publish time with a timezone.']);
            }
            if (! $data['post_id'] && ! $data['comic_chapter_id']) {
                throw ValidationException::withMessages(['post_id' => 'Link an owned draft to schedule publication.']);
            }
            if ($plan?->delivery_status === 'published') {
                throw ValidationException::withMessages(['stage' => 'This plan has already published. Create a new plan for another draft.']);
            }
            $duplicate = StudioPlan::where('user_id', $user->id)->where('stage', 'scheduled')
                ->when($plan, fn ($q) => $q->whereKeyNot($plan->id))
                ->where($data['post_id'] ? 'post_id' : 'comic_chapter_id', $data['post_id'] ?: $data['comic_chapter_id'])
                ->exists();
            if ($duplicate) {
                throw ValidationException::withMessages(['stage' => 'This draft already has a schedule. Edit or cancel that plan first.']);
            }
        }
        if ($data['stage'] === 'published' && ($data['post_id'] || $data['comic_chapter_id']) && ! $destinationPublished) {
            throw ValidationException::withMessages(['stage' => 'A linked draft must be published from Studio or by its schedule before marking it published.']);
        }

        return $data;
    }

    private function deliveryState(StudioPlan $plan): void
    {
        if ($plan->delivery_status === 'published') {
            return;
        }
        if ($plan->stage === 'published' && ($plan->post_id || $plan->comic_chapter_id)) {
            $plan->delivery_status = 'published';
            $plan->delivery_error = null;
            $plan->published_at = $plan->post_id
                ? Post::findOrFail($plan->post_id)->created_at
                : ComicChapter::findOrFail($plan->comic_chapter_id)->published_at;

            return;
        }
        $plan->delivery_status = $plan->stage === 'scheduled' ? 'pending' : null;
        $plan->delivery_error = null;
    }
}
