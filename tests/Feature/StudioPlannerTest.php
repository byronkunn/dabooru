<?php

namespace Tests\Feature;

use App\Http\Controllers\StudioPlanController;
use App\Models\Comic;
use App\Models\ComicChapter;
use App\Models\ComicPage;
use App\Models\Media;
use App\Models\Post;
use App\Models\PostMedia;
use App\Models\StudioPlan;
use App\Models\Tag;
use App\Models\User;
use App\Support\Moderation;
use App\Support\ScheduledStudioPlans;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class StudioPlannerTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        Storage::fake('local');
        // Keep the isolated suite usable before the host Studio routes are integrated.
        if (! Route::has('studio.plans.store')) {
            Route::middleware(['web', 'auth'])->group(function () {
                Route::post('/test-studio-plans', [StudioPlanController::class, 'store'])->name('studio.plans.store');
                Route::patch('/test-studio-plans/{plan}', [StudioPlanController::class, 'update'])->name('studio.plans.update');
                Route::delete('/test-studio-plans/{plan}', [StudioPlanController::class, 'destroy'])->name('studio.plans.destroy');
            });
            Route::getRoutes()->refreshNameLookups();
        }
    }

    public function test_private_crud_ownership_and_server_controlled_delivery_fields(): void
    {
        $owner = User::factory()->create();
        $other = User::factory()->create();
        $post = $this->draft($owner);
        $this->actingAs($owner)->post(route('studio.plans.store'), $this->input([
            'title' => 'Secret planner title', 'notes' => 'Private planning notes',
            'post_id' => $post->id, 'user_id' => $other->id,
            'delivery_status' => 'published', 'delivery_error' => 'forged',
            'deadline_at' => '2026-12-01T09:00:00-06:00',
        ]))->assertRedirect()->assertSessionHas('success');
        $plan = StudioPlan::firstOrFail();
        $this->assertSame($owner->id, $plan->user_id);
        $this->assertNull($plan->delivery_status);
        $this->assertNull($plan->delivery_error);
        $this->assertSame('2026-12-01T15:00:00+00:00', $plan->deadline_at->toIso8601String());
        $this->assertCount(1, StudioPlanController::plannerData($owner)['plans']);
        $this->assertCount(0, StudioPlanController::plannerData($other)['plans']);
        $this->assertCount(0, StudioPlanController::plannerData($other)['draftWorks']);
        $this->actingAs($other)->get(route('studio.index'))->assertOk()
            ->assertDontSee('Secret planner title')->assertDontSee('Private planning notes');
        $this->patch(route('studio.plans.update', $plan), [])->assertNotFound();
        $this->delete(route('studio.plans.destroy', $plan))->assertNotFound();
        $this->assertSame('Secret planner title', $plan->fresh()->title);
        $this->actingAs($owner)->patch(route('studio.plans.update', $plan), $this->input([
            'title' => 'Updated plan', 'stage' => 'in_progress', 'post_id' => $post->id,
        ]))->assertRedirect()->assertSessionHas('success');
        $this->assertSame('in_progress', $plan->fresh()->stage);
        $this->delete(route('studio.plans.destroy', $plan))->assertRedirect()->assertSessionHas('success');
        $this->assertDatabaseMissing('studio_plans', ['id' => $plan->id]);
        $this->assertTrue(Post::withoutGlobalScope('published')->findOrFail($post->id)->is_draft);
        Storage::disk('local')->assertExists("drafts/{$owner->id}/work.bin");
    }

    public function test_plans_are_paginated_without_exposing_another_users_plans(): void
    {
        $owner = User::factory()->create();
        $other = User::factory()->create();
        foreach (range(1, 21) as $number) {
            $plan = new StudioPlan(['title' => "Plan {$number}", 'stage' => 'idea', 'timezone' => 'UTC']);
            $plan->user_id = $owner->id;
            $plan->save();
        }
        $foreignPlan = new StudioPlan(['title' => 'Foreign plan', 'stage' => 'idea', 'timezone' => 'UTC']);
        $foreignPlan->user_id = $other->id;
        $foreignPlan->save();

        $this->actingAs($owner)->get(route('studio.index', ['tab' => 'planner']))->assertInertia(fn ($page) => $page
            ->has('plans', 20)
            ->where('plans.0.title', 'Plan 21')
            ->where('planStage', null)
            ->where('planCounts.idea', 21)->where('planCounts.ready', 0)
            ->where('planPages.nextPage', fn ($url) => str_contains($url, 'tab=planner') && str_contains($url, 'plans_page=2')));

        $this->get(route('studio.index', ['tab' => 'planner', 'plans_page' => 2]))->assertInertia(fn ($page) => $page
            ->has('plans', 1)
            ->where('plans.0.title', 'Plan 1')
            ->where('planPages.previousPage', fn ($url) => str_contains($url, 'plans_page=1')));

        $ready = new StudioPlan(['title' => 'Ready plan', 'stage' => 'ready', 'timezone' => 'UTC']);
        $ready->user_id = $owner->id;
        $ready->save();
        $this->get(route('studio.index', ['tab' => 'planner', 'stage' => 'ready']))->assertInertia(fn ($page) => $page
            ->has('plans', 1)->where('plans.0.title', 'Ready plan')->where('planStage', 'ready')
            ->where('planCounts.idea', 21)->where('planCounts.ready', 1)
            ->where('planPages.nextPage', null));
        $this->get(route('studio.index', ['tab' => 'planner', 'stage' => 'bogus']))->assertSessionHasErrors('stage');
    }

    public function test_guests_cannot_create_edit_or_delete_plans(): void
    {
        $owner = User::factory()->create();
        $plan = new StudioPlan($this->input());
        $plan->user_id = $owner->id;
        $plan->save();
        $this->post(route('studio.plans.store'), $this->input())->assertRedirect(route('login'));
        $this->patch(route('studio.plans.update', $plan), $this->input())->assertRedirect(route('login'));
        $this->delete(route('studio.plans.destroy', $plan))->assertRedirect(route('login'));
        $this->assertDatabaseCount('studio_plans', 1);
    }

    public function test_dates_notes_timezone_and_required_schedule_link_are_validated(): void
    {
        $owner = User::factory()->create();
        $this->actingAs($owner);
        foreach ([
            ['notes' => str_repeat('x', 10001)],
            ['title' => str_repeat('x', 161)],
            ['timezone' => 'Not/AZone'],
            ['deadline_at' => '2026-12-01T09:00'],
            ['deadline_at' => 'not a date'],
            ['stage' => 'unknown'],
            ['stage' => 'scheduled', 'publish_at' => null],
            ['stage' => 'scheduled', 'publish_at' => now()->subDay()->toIso8601String()],
            ['stage' => 'scheduled', 'publish_at' => now()->addDay()->toIso8601String()],
        ] as $changes) {
            $this->post(route('studio.plans.store'), $this->input($changes))->assertSessionHasErrors();
        }
        $this->assertDatabaseCount('studio_plans', 0);
    }

    public function test_links_must_be_owned_unpublished_and_unambiguous(): void
    {
        $owner = User::factory()->create();
        $other = User::factory()->create();
        $foreign = $this->draft($other);
        $owned = $this->draft($owner);
        $published = Post::create(['user_id' => $owner->id, 'type' => 'image', 'rating' => 'sfw']);
        $chapter = $this->chapter($other);
        $this->actingAs($owner);
        foreach ([
            ['post_id' => $foreign->id],
            ['post_id' => $published->id, 'stage' => 'scheduled', 'publish_at' => now()->addHour()->toIso8601String()],
            ['post_id' => 999999],
            ['comic_chapter_id' => $chapter->id],
            ['comic_chapter_id' => 999999],
            ['post_id' => $owned->id, 'comic_chapter_id' => $chapter->id],
            ['post_id' => $owned->id, 'stage' => 'published'],
        ] as $changes) {
            $this->post(route('studio.plans.store'), $this->input($changes))->assertSessionHasErrors();
        }
        $this->assertDatabaseCount('studio_plans', 0);
    }

    public function test_due_work_publishes_tags_and_keeps_upload_paths_safe_and_is_idempotent(): void
    {
        $owner = User::factory()->create();
        $reader = User::factory()->create();
        $post = $this->draft($owner);
        $media = $post->media()->first()->media;
        $plan = $this->schedule($owner, ['post_id' => $post->id]);
        $this->actingAs($reader)->get(route('posts.show', $post->id))->assertNotFound();
        $this->get($media->url())->assertNotFound();
        $this->assertSame(['published' => 0, 'failed' => 0], ScheduledStudioPlans::deliverDue());
        $this->travel(2)->hours();
        $this->assertSame(['published' => 1, 'failed' => 0], ScheduledStudioPlans::deliverDue());
        $this->assertSame(['published' => 0, 'failed' => 0], ScheduledStudioPlans::deliverDue());
        $this->assertFalse(Post::findOrFail($post->id)->is_draft);
        $this->assertNull(Post::findOrFail($post->id)->draft_tags);
        $this->assertSame(['ink'], $post->tags()->pluck('name')->all());
        $this->assertSame('published', $plan->fresh()->stage);
        $this->assertSame('published', $plan->fresh()->delivery_status);
        $this->assertNotNull($plan->fresh()->published_at);
        $this->get(route('posts.show', $post->id))->assertOk();
        $response = $this->get($media->url())->assertOk();
        $this->assertStringContainsString('no-store', $response->headers->get('Cache-Control'));
        $this->assertSame('local', $media->fresh()->disk);
        Storage::disk('local')->assertExists($media->path);
        $this->assertDatabaseCount('post_media', 1);
        $newDraft = $this->draft($owner);
        $this->actingAs($owner)->patch(route('studio.plans.update', $plan), $this->input([
            'stage' => 'published', 'post_id' => $newDraft->id,
        ]))->assertSessionHasErrors('post_id');
        $this->assertSame($post->id, $plan->fresh()->post_id);
    }

    public function test_due_chapter_uses_normal_publishing_and_only_creates_one_feed_post(): void
    {
        $owner = User::factory()->create();
        $chapter = $this->chapter($owner);
        $plan = $this->schedule($owner, ['comic_chapter_id' => $chapter->id]);
        $this->travel(2)->hours();
        $this->assertSame(['published' => 1, 'failed' => 0], ScheduledStudioPlans::deliverDue());
        ScheduledStudioPlans::deliverDue();
        $this->assertNotNull($chapter->fresh()->published_at);
        $this->assertNotNull($chapter->comic->fresh()->last_chapter_at);
        $this->assertNull($chapter->fresh()->draft_tags);
        $feed = Post::where('comic_chapter_id', $chapter->id)->sole();
        $this->assertSame(['chapter-ink'], $feed->tags()->pluck('name')->all());
        $this->assertSame($chapter->pages()->first()->media_id, $feed->media()->first()->media_id);
        $this->assertSame('published', $plan->fresh()->delivery_status);
        $this->assertDatabaseCount('comic_pages', 1);
    }

    public function test_cancellation_prevents_publication_and_duplicate_schedules_are_rejected(): void
    {
        $owner = User::factory()->create();
        $post = $this->draft($owner);
        $plan = $this->schedule($owner, ['post_id' => $post->id]);
        $this->post(route('studio.plans.store'), $this->input([
            'stage' => 'scheduled', 'publish_at' => now()->addHour()->toIso8601String(), 'post_id' => $post->id,
        ]))->assertSessionHasErrors('stage');
        $this->patch(route('studio.plans.update', $plan), $this->input([
            'stage' => 'ready', 'post_id' => $post->id,
        ]))->assertRedirect()->assertSessionHasNoErrors();
        $this->assertNull($plan->fresh()->delivery_status);
        $this->travel(2)->hours();
        $this->assertSame(['published' => 0, 'failed' => 0], ScheduledStudioPlans::deliverDue());
        $this->assertTrue($post->fresh()->is_draft);
    }

    public function test_banned_owner_and_moderation_removals_persist_failures_without_publishing(): void
    {
        $banned = User::factory()->create();
        $owner = User::factory()->create();
        $bannedDraft = $this->draft($banned);
        $hiddenDraft = $this->draft($owner);
        $first = $this->schedule($banned, ['post_id' => $bannedDraft->id]);
        $second = $this->schedule($owner, ['post_id' => $hiddenDraft->id]);
        $banned->forceFill(['banned_at' => now()])->save();
        Moderation::hide($hiddenDraft);
        $this->travel(2)->hours();
        $this->assertSame(['published' => 0, 'failed' => 2], ScheduledStudioPlans::deliverDue());
        foreach ([$first, $second] as $plan) {
            $this->assertSame('scheduled', $plan->fresh()->stage);
            $this->assertSame('failed', $plan->fresh()->delivery_status);
            $this->assertNotEmpty($plan->fresh()->delivery_error);
            $this->assertNull($plan->fresh()->published_at);
        }
        $this->assertSame(['published' => 0, 'failed' => 0], ScheduledStudioPlans::deliverDue());
        $this->assertSame(0, Post::count());
    }

    public function test_missing_upload_failure_can_be_reviewed_and_explicitly_rescheduled(): void
    {
        $owner = User::factory()->create();
        $post = $this->draft($owner);
        $media = $post->media()->first()->media;
        $plan = $this->schedule($owner, ['post_id' => $post->id]);
        Storage::disk('local')->delete($media->path);
        Log::spy();
        $this->travel(2)->hours();
        $this->assertSame(['published' => 0, 'failed' => 1], ScheduledStudioPlans::deliverDue());
        Log::shouldHaveReceived('warning')->once()->with('Scheduled Studio publication failed.', ['plan_id' => $plan->id]);
        $this->assertTrue($post->fresh()->is_draft);
        $this->assertSame('failed', $plan->fresh()->delivery_status);
        $this->assertStringContainsString('missing', $plan->fresh()->delivery_error);
        $listed = StudioPlanController::plannerData($owner)['plans']->sole();
        $this->assertSame('failed', $listed->delivery_status);
        $this->assertSame($plan->fresh()->delivery_error, $listed->delivery_error);
        Storage::disk('local')->put($media->path, 'restored upload');
        $this->patch(route('studio.plans.update', $plan), $this->input([
            'post_id' => $post->id, 'stage' => 'scheduled', 'publish_at' => now()->addHour()->toIso8601String(),
        ]))->assertRedirect()->assertSessionHasNoErrors();
        $this->assertSame('pending', $plan->fresh()->delivery_status);
        $this->assertNull($plan->fresh()->delivery_error);
        $this->travel(2)->hours();
        $this->assertSame(['published' => 1, 'failed' => 0], ScheduledStudioPlans::deliverDue());
    }

    public function test_private_message_media_is_never_published_by_a_plan(): void
    {
        $owner = User::factory()->create();
        $post = $this->draft($owner);
        $media = $post->media()->first()->media;
        $media->update(['path' => "dm/{$owner->id}/private.bin"]);
        Storage::disk('local')->put($media->path, 'private message');
        $plan = $this->schedule($owner, ['post_id' => $post->id]);
        $this->travel(2)->hours();
        $this->assertSame(['published' => 0, 'failed' => 1], ScheduledStudioPlans::deliverDue());
        $this->assertTrue($post->fresh()->is_draft);
        $this->assertStringContainsString('private media', $plan->fresh()->delivery_error);
        $this->assertSame('', $media->url());
    }

    public function test_deleting_a_scheduled_plan_cancels_delivery_without_deleting_the_draft(): void
    {
        $owner = User::factory()->create();
        $post = $this->draft($owner);
        $plan = $this->schedule($owner, ['post_id' => $post->id]);
        $this->delete(route('studio.plans.destroy', $plan))->assertRedirect()->assertSessionHasNoErrors();
        $this->travel(2)->hours();
        $this->assertSame(['published' => 0, 'failed' => 0], ScheduledStudioPlans::deliverDue());
        $this->assertTrue($post->fresh()->is_draft);
        $this->assertDatabaseCount('post_media', 1);
    }

    public function test_manually_published_owned_work_and_chapter_can_be_tracked_without_republishing(): void
    {
        $owner = User::factory()->create();
        $post = $this->draft($owner);
        $chapter = $this->chapter($owner);
        $workPlan = $this->schedule($owner, ['post_id' => $post->id]);
        $chapterPlan = $this->schedule($owner, ['comic_chapter_id' => $chapter->id]);
        $this->patch(route('studio.works.update', $post), [
            'title' => $post->title, 'caption' => null, 'rating' => 'sfw', 'tags' => 'ink',
            'order' => $post->media()->pluck('id')->all(), 'publish' => true,
        ])->assertRedirect()->assertSessionHasNoErrors();
        $this->patch(route('studio.chapters.update', $chapter), [
            'title' => $chapter->title, 'rating' => 'sfw', 'tags' => 'chapter-ink',
            'order' => $chapter->pages()->pluck('id')->all(), 'publish' => true,
        ])->assertRedirect()->assertSessionHasNoErrors();

        foreach ([
            [$workPlan, ['post_id' => $post->id], $post->fresh()->created_at],
            [$chapterPlan, ['comic_chapter_id' => $chapter->id], $chapter->fresh()->published_at],
        ] as [$plan, $destination, $publishedAt]) {
            $this->patch(route('studio.plans.update', $plan), $this->input($destination + ['stage' => 'published']))
                ->assertRedirect()->assertSessionHasNoErrors();
            $this->assertSame('published', $plan->fresh()->delivery_status);
            $this->assertTrue($publishedAt->equalTo($plan->fresh()->published_at));
            $this->patch(route('studio.plans.update', $plan), $this->input($destination + [
                'stage' => 'scheduled', 'publish_at' => now()->addHour()->toIso8601String(),
            ]))->assertSessionHasErrors();
        }
        $this->travel(2)->hours();
        $this->assertSame(['published' => 0, 'failed' => 0], ScheduledStudioPlans::deliverDue());
        $this->assertDatabaseCount('posts', 2);
        $this->assertDatabaseCount('post_media', 2);
        $other = User::factory()->create();
        $this->actingAs($other)->post(route('studio.plans.store'), $this->input([
            'stage' => 'published', 'post_id' => $post->id,
        ]))->assertSessionHasErrors('post_id');
        $this->actingAs($owner)->post(route('studio.plans.store'), $this->input([
            'stage' => 'published', 'post_id' => $post->id,
        ]))->assertSessionHasNoErrors();
        $this->assertSame('published', StudioPlan::latest('id')->firstOrFail()->delivery_status);
    }

    public function test_tag_publication_failure_rolls_back_the_entire_chapter_and_is_visible(): void
    {
        $owner = User::factory()->create();
        $chapter = $this->chapter($owner);
        $one = Tag::create(['name' => 'chapter-ink']);
        $two = Tag::create(['name' => 'tag-loop', 'merged_into_id' => $one->id]);
        $one->update(['merged_into_id' => $two->id]);
        $plan = $this->schedule($owner, ['comic_chapter_id' => $chapter->id]);
        $this->travel(2)->hours();
        $this->assertSame(['published' => 0, 'failed' => 1], ScheduledStudioPlans::deliverDue());
        $this->assertNull($chapter->fresh()->published_at);
        $this->assertSame(['chapter-ink'], $chapter->fresh()->draft_tags);
        $this->assertNull($chapter->comic->fresh()->last_chapter_at);
        $this->assertDatabaseCount('posts', 0);
        $this->assertDatabaseCount('post_media', 0);
        $this->assertSame('failed', $plan->fresh()->delivery_status);
        $this->assertStringContainsString('circular', $plan->fresh()->delivery_error);
    }

    public function test_deleted_or_transferred_chapter_never_publishes(): void
    {
        $owner = User::factory()->create();
        $other = User::factory()->create();
        $chapter = $this->chapter($owner);
        $plan = $this->schedule($owner, ['comic_chapter_id' => $chapter->id]);
        $chapter->comic->update(['user_id' => $other->id]);
        $this->travel(2)->hours();
        $this->assertSame(['published' => 0, 'failed' => 1], ScheduledStudioPlans::deliverDue());
        $this->assertStringContainsString('owned', $plan->fresh()->delivery_error);
        $this->assertNull($chapter->fresh()->published_at);
        $chapter->comic->update(['user_id' => $owner->id]);
        $this->patch(route('studio.plans.update', $plan), $this->input([
            'comic_chapter_id' => $chapter->id, 'stage' => 'scheduled', 'publish_at' => now()->addHour()->toIso8601String(),
        ]))->assertSessionHasNoErrors();
        $chapter->delete();
        $this->travel(2)->hours();
        $this->assertSame(['published' => 0, 'failed' => 1], ScheduledStudioPlans::deliverDue());
        $this->assertStringContainsString('missing', $plan->fresh()->delivery_error);
        $this->assertDatabaseCount('posts', 0);
    }

    private function input(array $changes = []): array
    {
        return array_replace([
            'title' => 'Next release', 'notes' => null, 'stage' => 'idea',
            'timezone' => 'America/Chicago', 'deadline_at' => null, 'publish_at' => null,
            'post_id' => null, 'comic_chapter_id' => null,
        ], $changes);
    }

    private function schedule(User $owner, array $destination): StudioPlan
    {
        $this->actingAs($owner)->post(route('studio.plans.store'), $this->input($destination + [
            'stage' => 'scheduled', 'publish_at' => now()->addHour()->utc()->toIso8601String(),
        ]))->assertRedirect()->assertSessionHasNoErrors();

        return StudioPlan::latest('id')->firstOrFail();
    }

    private function draft(User $owner): Post
    {
        $post = Post::create([
            'user_id' => $owner->id, 'title' => 'Unreleased work', 'type' => 'image',
            'rating' => 'sfw', 'is_draft' => true, 'draft_tags' => ['ink'],
        ]);
        PostMedia::create(['post_id' => $post->id, 'media_id' => $this->media($owner, 'work')->id, 'position' => 0]);

        return $post;
    }

    private function chapter(User $owner): ComicChapter
    {
        $comic = Comic::create(['user_id' => $owner->id, 'title' => 'Ink series', 'slug' => 'ink-'.$owner->id, 'rating' => 'sfw']);
        $chapter = ComicChapter::create([
            'comic_id' => $comic->id, 'number' => 1, 'title' => 'Unreleased chapter', 'rating' => 'sfw',
            'draft_tags' => ['chapter-ink'],
        ]);
        ComicPage::create(['comic_chapter_id' => $chapter->id, 'media_id' => $this->media($owner, 'chapter')->id, 'position' => 1]);

        return $chapter;
    }

    private function media(User $owner, string $name): Media
    {
        $path = "drafts/{$owner->id}/{$name}.bin";
        Storage::disk('local')->put($path, 'existing sanitized upload fixture');

        return Media::create([
            'user_id' => $owner->id, 'disk' => 'local', 'path' => $path,
            'kind' => 'image', 'status' => 'ready', 'mime_type' => 'image/png',
        ]);
    }
}
