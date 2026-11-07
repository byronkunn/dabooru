<?php

namespace Tests\Feature;

use App\Models\AppNotification;
use App\Models\KnowledgeRevision;
use App\Models\Post;
use App\Models\Tag;
use App\Models\User;
use App\Models\WikiPage;
use App\Support\KnowledgeService;
use Database\Seeders\KnowledgeSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use Tests\TestCase;

class KnowledgeTest extends TestCase
{
    use RefreshDatabase;

    private function member(array $attributes = []): User
    {
        return User::factory()->create($attributes);
    }

    private function moderator(): User
    {
        $user = $this->member(['show_explicit' => true]);
        $user->forceFill(['is_admin' => true])->save();

        return $user;
    }

    private function tag(string $name = 'watercolor'): Tag
    {
        return Tag::create(['name' => $name]);
    }

    private function payload(Tag $tag, array $changes = []): array
    {
        return array_replace(KnowledgeService::snapshot($tag), [
            'base_version' => $tag->fresh()->version,
            'summary' => 'Explain when this tag should be used.',
        ], $changes);
    }

    private function url(string $action, string $type, int $id): string
    {
        return route('knowledge.'.$action, ['type' => $type, 'id' => $id]);
    }

    private function approve(KnowledgeRevision $revision): void
    {
        $this->actingAs($this->moderator())->post(route('admin.knowledge.review', $revision), [
            'action' => 'accept', 'reason' => 'Reviewed the proposed definition.',
        ])->assertSessionHasNoErrors()->assertRedirect();
    }

    public function test_directory_has_all_approved_categories(): void
    {
        $this->tag();
        $this->get('/tags')->assertOk()->assertInertia(fn ($page) => $page
            ->component('Knowledge/Index')->where('categories', Tag::CATEGORIES)->has('tags.data', 1));
        $this->assertContains('anatomy', Tag::CATEGORIES);
        $this->assertContains('content warnings', Tag::CATEGORIES);
    }

    public function test_new_member_edits_wait_for_review_and_publish_after_approval(): void
    {
        $tag = $this->tag();
        $this->actingAs($this->member())->post($this->url('propose', 'tag', $tag->id), $this->payload($tag, [
            'category' => 'medium', 'wiki' => 'Pigment suspended in water.',
        ]))->assertSessionHasNoErrors()->assertRedirect();
        $this->assertNull($tag->fresh()->wiki);
        $revision = KnowledgeRevision::firstOrFail();
        $this->assertSame('pending', $revision->status);
        $this->approve($revision);
        $this->assertSame('Pigment suspended in water.', $tag->fresh()->wiki);
        $this->assertSame('medium', $tag->fresh()->category);
        $this->assertSame(1, $tag->fresh()->version);
    }

    public function test_unverified_restricted_and_banned_accounts_cannot_contribute(): void
    {
        $tag = $this->tag();
        foreach ([
            ['email_verified_at' => null],
            ['contributions_restricted' => true],
            ['banned_at' => now()],
        ] as $attributes) {
            $user = $this->member();
            $user->forceFill($attributes)->save();
            $this->actingAs($user)->post($this->url('propose', 'tag', $tag->id),
                $this->payload($tag, ['wiki' => 'Definition.']))->assertForbidden();
        }
        $this->assertDatabaseCount('knowledge_revisions', 0);
    }

    public function test_ordinary_members_cannot_review_or_lock_pages(): void
    {
        $tag = $this->tag();
        $user = $this->member();
        $this->actingAs($user)->get(route('admin.knowledge'))->assertForbidden();
        $this->post(route('admin.knowledge.lock', ['type' => 'tag', 'id' => $tag->id]), ['locked' => true])->assertForbidden();
    }

    public function test_trust_requires_five_independent_moderator_approvals(): void
    {
        $user = $this->member();
        $moderator = $this->moderator();
        $tag = $this->tag();
        for ($i = 0; $i < 5; $i++) {
            KnowledgeRevision::create([
                'user_id' => $user->id, 'reviewer_id' => $moderator->id,
                'target_type' => 'tag', 'target_id' => $tag->id,
                'before' => [], 'after' => [], 'base_version' => $i,
                'summary' => 'Reviewed contribution', 'status' => 'accepted',
            ]);
        }
        $this->assertTrue(KnowledgeService::trusted($user));
        $this->actingAs($user)->post($this->url('propose', 'tag', $tag->id),
            $this->payload($tag, ['wiki' => 'An ordinary edit.']))->assertSessionHasNoErrors();
        $this->assertSame('An ordinary edit.', $tag->fresh()->wiki);
        $this->post($this->url('propose', 'tag', $tag->id),
            $this->payload($tag, ['aliases' => ['aqua painting']]))->assertSessionHasNoErrors();
        $this->assertSame('pending', KnowledgeRevision::latest('id')->first()->status);
    }

    public function test_stale_edits_cannot_overwrite_newer_work(): void
    {
        $tag = $this->tag();
        $payload = $this->payload($tag, ['wiki' => 'A stale definition.']);
        $tag->increment('version');
        $this->actingAs($this->member())->post($this->url('propose', 'tag', $tag->id), $payload)
            ->assertSessionHasErrors('base_version');
        $this->assertDatabaseCount('knowledge_revisions', 0);
    }

    public function test_pending_revision_becomes_stale_before_approval(): void
    {
        $tag = $this->tag();
        $this->actingAs($this->member())->post($this->url('propose', 'tag', $tag->id),
            $this->payload($tag, ['wiki' => 'Pending definition.']));
        $tag->increment('version');
        $this->actingAs($this->moderator())->post(route('admin.knowledge.review', KnowledgeRevision::first()), [
            'action' => 'accept', 'reason' => 'Reviewed.',
        ])->assertSessionHasErrors('base_version');
        $this->assertNull($tag->fresh()->wiki);
        $this->assertSame('pending', KnowledgeRevision::first()->status);
    }

    public function test_pending_edits_are_private_to_editor_and_moderators(): void
    {
        $tag = $this->tag();
        $this->actingAs($this->member())->post($this->url('propose', 'tag', $tag->id),
            $this->payload($tag, ['wiki' => 'Pending text.']));
        $revision = KnowledgeRevision::first();
        $this->actingAs($this->member())->get(route('knowledge.revision', $revision))->assertForbidden();
    }

    public function test_locked_pages_reject_member_edits_and_discussions(): void
    {
        $tag = $this->tag();
        $tag->update(['locked' => true]);
        $this->actingAs($this->member())->post($this->url('propose', 'tag', $tag->id),
            $this->payload($tag, ['wiki' => 'Changed text.']))->assertStatus(423);
        $this->post($this->url('discuss', 'tag', $tag->id), ['body' => 'Discussion.'])->assertStatus(423);
    }

    public function test_aliases_resolve_in_upload_sync_search_and_autocomplete(): void
    {
        $tag = $this->tag();
        DB::table('tag_aliases')->insert(['name' => 'aqua painting', 'tag_id' => $tag->id]);
        $post = Post::create(['type' => 'image', 'rating' => 'sfw', 'title' => 'Study']);
        Tag::syncFor($post, ['aqua painting']);
        $this->assertSame(['watercolor'], $post->tags()->pluck('name')->all());
        $this->get('/search?q='.urlencode('tag:"aqua painting"'))->assertOk()->assertInertia(fn ($page) => $page->where('counts.works', 1));
        $this->get('/tags/autocomplete?q=aqua')->assertOk()->assertJsonPath('tags.0.name', 'watercolor');
        $this->get('/?window=all&tag='.urlencode('aqua painting'))->assertOk()->assertInertia(fn ($page) => $page->where('filters.tag', 'watercolor'));
    }

    public function test_rename_retains_old_name_as_alias(): void
    {
        $tag = $this->tag();
        $this->actingAs($this->moderator())->post($this->url('propose', 'tag', $tag->id),
            $this->payload($tag, ['name' => 'watercolour']))->assertSessionHasNoErrors();
        $this->assertSame('watercolour', Tag::canonicalName('watercolor'));
    }

    public function test_alias_collision_rolls_back_entire_edit(): void
    {
        $tag = $this->tag();
        $this->tag('digital painting');
        $this->actingAs($this->moderator())->post($this->url('propose', 'tag', $tag->id),
            $this->payload($tag, ['wiki' => 'Do not persist this.', 'aliases' => ['digital painting']]))
            ->assertSessionHasErrors('aliases');
        $this->assertNull($tag->fresh()->wiki);
        $this->assertDatabaseCount('knowledge_revisions', 0);
    }

    public function test_implications_apply_transitively_and_cycles_are_rejected(): void
    {
        $tag = $this->tag();
        $medium = $this->tag('traditional media');
        $art = $this->tag('art');
        DB::table('tag_relations')->insert([
            ['tag_id' => $tag->id, 'related_tag_id' => $medium->id, 'kind' => 'implies'],
            ['tag_id' => $medium->id, 'related_tag_id' => $art->id, 'kind' => 'implies'],
        ]);
        $post = Post::create(['type' => 'image', 'rating' => 'sfw']);
        Tag::syncFor($post, ['watercolor']);
        $this->assertSame(['art', 'traditional media', 'watercolor'], $post->tags()->pluck('name')->all());
        $this->actingAs($this->moderator())->post($this->url('propose', 'tag', $art->id),
            $this->payload($art, ['implies' => [$tag->id]]))->assertSessionHasErrors('implies');
    }

    public function test_community_post_edit_cannot_change_rating_or_owner(): void
    {
        $owner = $this->member();
        $post = Post::create(['user_id' => $owner->id, 'type' => 'image', 'rating' => 'sfw', 'artist_name' => 'External credit']);
        $this->actingAs($this->member())->post($this->url('propose', 'post', $post->id), [
            'tags' => ['watercolor'], 'base_version' => 0, 'summary' => 'Add medium.',
            'rating' => 'explicit', 'user_id' => 999, 'artist_name' => 'Wrong credit',
        ])->assertSessionHasNoErrors();
        $this->approve(KnowledgeRevision::first());
        $this->assertSame('sfw', $post->fresh()->rating);
        $this->assertSame($owner->id, $post->fresh()->user_id);
        $this->assertSame('External credit', $post->fresh()->artist_name);
        $this->assertSame(['watercolor'], $post->tags()->pluck('name')->all());
    }

    public function test_non_moderator_cannot_assert_artist_membership(): void
    {
        $tag = $this->tag('external creator');
        $this->actingAs($this->member())->post($this->url('propose', 'tag', $tag->id), $this->payload($tag, [
            'category' => 'artist', 'wiki' => 'Public artist description.',
            'artist_status' => 'member', 'member_id' => $this->member()->id,
        ]))->assertSessionHasNoErrors();
        $this->assertSame('unverified', KnowledgeRevision::first()->after['artist_status']);
        $this->assertNull(KnowledgeRevision::first()->after['member_id']);
    }

    public function test_confirmed_artist_status_cannot_be_removed_by_reclassification(): void
    {
        $member = $this->member();
        $tag = $this->tag('confirmed artist');
        $tag->update(['category' => 'artist', 'artist_status' => 'member', 'member_id' => $member->id]);
        $this->actingAs($this->member())->post($this->url('propose', 'tag', $tag->id),
            $this->payload($tag, ['category' => 'general']))->assertSessionHasErrors('category');
        $this->assertSame('member', $tag->fresh()->artist_status);
        $this->assertSame($member->id, $tag->fresh()->member_id);
    }

    public function test_explicit_wiki_pages_and_revisions_do_not_bypass_preferences(): void
    {
        $tag = $this->tag();
        $tag->update(['wiki_rating' => 'explicit']);
        $this->get($this->url('show', 'tag', $tag->id))->assertForbidden();
        $this->get('/tags')->assertInertia(fn ($page) => $page->has('tags.data', 0));
        $this->get('/tags/autocomplete?q=water')->assertJsonCount(0, 'tags');
        $this->actingAs($this->moderator())->post($this->url('propose', 'tag', $tag->id),
            $this->payload($tag, ['wiki' => 'An adult-rated definition.']))->assertSessionHasNoErrors();
        $revision = KnowledgeRevision::first();
        $this->actingAs($this->member(['show_explicit' => false]))->get(route('knowledge.revision', $revision))->assertForbidden();
    }

    public function test_examples_and_counts_respect_content_preferences_and_blocks(): void
    {
        $viewer = $this->member(['show_explicit' => false]);
        $owner = $this->member();
        $tag = $this->tag();
        foreach (['sfw', 'explicit'] as $rating) {
            $post = Post::create(['user_id' => $owner->id, 'type' => 'image', 'rating' => $rating]);
            Tag::syncFor($post, [$tag->name]);
        }
        $this->actingAs($viewer)->get($this->url('show', 'tag', $tag->id))
            ->assertOk()->assertInertia(fn ($page) => $page->where('works.total', 1));
        DB::table('blocks')->insert(['blocker_id' => $viewer->id, 'blocked_id' => $owner->id, 'created_at' => now(), 'updated_at' => now()]);
        $this->get('/tags/autocomplete?q=water')->assertJsonPath('tags.0.count', 0);
        $this->get($this->url('show', 'tag', $tag->id))->assertInertia(fn ($page) => $page->where('works.total', 0));
    }

    public function test_wiki_markdown_strips_raw_html_unsafe_links_and_embeds(): void
    {
        $wiki = WikiPage::create(['title' => 'Tagging guide', 'body' => '<script>alert(1)</script> [bad](javascript:alert(1)) ![embed](https://example.com/image.png)']);
        $this->get($this->url('show', 'wiki', $wiki->id))->assertOk()->assertInertia(fn ($page) => $page
            ->where('html', fn ($html) => ! str_contains($html, '<script') && ! str_contains($html, 'href="javascript:') && ! str_contains($html, '<img')));
    }

    public function test_watching_creates_notifications_after_approval(): void
    {
        $tag = $this->tag();
        $watcher = $this->member();
        $this->actingAs($watcher)->post($this->url('watch', 'tag', $tag->id), ['watching' => true])->assertSessionHasNoErrors();
        $this->actingAs($this->member())->post($this->url('propose', 'tag', $tag->id),
            $this->payload($tag, ['wiki' => 'Updated definition.']));
        $this->approve(KnowledgeRevision::first());
        $this->assertTrue(AppNotification::where('user_id', $watcher->id)->where('type', 'knowledge')->exists());
        $this->actingAs($watcher)->post($this->url('watch', 'tag', $tag->id), ['watching' => false]);
        $this->assertDatabaseMissing('knowledge_watches', ['user_id' => $watcher->id]);
    }

    public function test_bulk_preview_approval_and_rollback_preserve_exact_work_tags(): void
    {
        $tag = $this->tag();
        $destination = $this->tag('digital painting');
        $post = Post::create(['type' => 'image', 'rating' => 'sfw']);
        Tag::syncFor($post, [$tag->name]);
        $this->actingAs($this->member())->post(route('knowledge.bulk', $tag->id), [
            'replacement_id' => $destination->id, 'post_ids' => [$post->id],
            'base_version' => 0, 'summary' => 'Correct medium across selected work.',
        ])->assertSessionHasNoErrors();
        $revision = KnowledgeRevision::first();
        $this->assertSame(['watercolor'], $revision->before['posts'][$post->id]['tags']);
        $this->assertSame(['digital painting'], $revision->after['posts'][$post->id]['tags']);
        $this->approve($revision);
        $this->assertSame(['digital painting'], $post->tags()->pluck('name')->all());
        $this->post(route('admin.knowledge.review', $revision), ['action' => 'revert', 'reason' => 'Restore original medium.'])->assertSessionHasNoErrors();
        $this->assertSame(['watercolor'], $post->tags()->pluck('name')->all());
        $this->assertSame('reverted', $revision->fresh()->status);
    }

    public function test_bulk_approval_rejects_work_changed_since_preview(): void
    {
        $tag = $this->tag();
        $destination = $this->tag('digital painting');
        $post = Post::create(['type' => 'image', 'rating' => 'sfw']);
        Tag::syncFor($post, [$tag->name]);
        $this->actingAs($this->member())->post(route('knowledge.bulk', $tag->id), [
            'replacement_id' => $destination->id, 'post_ids' => [$post->id],
            'base_version' => 0, 'summary' => 'Correct medium.',
        ]);
        Tag::syncFor($post, ['watercolor', 'portrait']);
        $this->actingAs($this->moderator())->post(route('admin.knowledge.review', KnowledgeRevision::first()), [
            'action' => 'accept', 'reason' => 'Review bulk edit.',
        ])->assertSessionHasErrors('revision');
        $this->assertSame(['portrait', 'watercolor'], $post->tags()->pluck('name')->all());
    }

    public function test_even_moderators_must_review_bulk_preview_before_publishing(): void
    {
        $tag = $this->tag();
        $destination = $this->tag('digital painting');
        $post = Post::create(['type' => 'image', 'rating' => 'sfw']);
        Tag::syncFor($post, [$tag->name]);
        $this->actingAs($this->moderator())->post(route('knowledge.bulk', $tag->id), [
            'replacement_id' => $destination->id, 'post_ids' => [$post->id],
            'base_version' => 0, 'summary' => 'Prepare reviewed bulk change.',
        ])->assertSessionHasNoErrors();
        $this->assertSame('pending', KnowledgeRevision::first()->status);
        $this->assertSame(['watercolor'], $post->tags()->pluck('name')->all());
    }

    public function test_merge_preserves_old_name_and_page_links(): void
    {
        $tag = $this->tag();
        $destination = $this->tag('watercolour');
        $this->actingAs($this->moderator())->post($this->url('propose', 'tag', $tag->id),
            $this->payload($tag, ['merged_into_id' => $destination->id]))->assertSessionHasNoErrors();
        $this->assertSame('watercolour', Tag::canonicalName('watercolor'));
        $this->get($this->url('show', 'tag', $tag->id))->assertRedirect($this->url('show', 'tag', $destination->id));
    }

    public function test_merge_requires_reviewed_retagging_before_redirect(): void
    {
        $tag = $this->tag();
        $destination = $this->tag('watercolour');
        $post = Post::create(['type' => 'image', 'rating' => 'sfw']);
        Tag::syncFor($post, [$tag->name]);
        $this->actingAs($this->moderator())->post($this->url('propose', 'tag', $tag->id),
            $this->payload($tag, ['merged_into_id' => $destination->id]))->assertSessionHasErrors('merged_into_id');
        $this->assertNull($tag->fresh()->merged_into_id);
    }

    public function test_revert_refuses_to_overwrite_later_definition_changes(): void
    {
        $tag = $this->tag();
        $this->actingAs($this->moderator())->post($this->url('propose', 'tag', $tag->id), $this->payload($tag, ['wiki' => 'First definition.']));
        $revision = KnowledgeRevision::first();
        $this->post($this->url('propose', 'tag', $tag->id), $this->payload($tag, ['wiki' => 'Later definition.']));
        $this->post(route('admin.knowledge.review', $revision), ['action' => 'revert', 'reason' => 'Restore old text.'])
            ->assertSessionHasErrors('revision');
        $this->assertSame('Later definition.', $tag->fresh()->wiki);
    }

    public function test_discussion_reports_can_be_resolved_by_hiding_vandalism(): void
    {
        $tag = $this->tag();
        $this->actingAs($this->member())->post($this->url('discuss', 'tag', $tag->id), ['body' => 'Please discuss this definition.'])->assertSessionHasNoErrors();
        $discussion = DB::table('knowledge_discussions')->first();
        $this->post(route('knowledge.report'), ['target_type' => 'discussion', 'target_id' => $discussion->id, 'reason' => 'Needs moderation.'])
            ->assertSessionHasNoErrors();
        $this->actingAs($this->moderator())->post(route('admin.knowledge.report', DB::table('knowledge_reports')->value('id')), ['hide_discussion' => true])
            ->assertSessionHasNoErrors();
        $this->assertDatabaseHas('knowledge_discussions', ['id' => $discussion->id, 'hidden' => true]);
    }

    public function test_owner_can_edit_a_work_with_more_than_ten_community_tags(): void
    {
        $owner = $this->member();
        $post = Post::create(['user_id' => $owner->id, 'type' => 'image', 'rating' => 'sfw']);
        $names = array_map(fn ($i) => 'tag '.$i, range(1, 15));
        Tag::syncFor($post, $names);
        $this->actingAs($owner)->patch(route('posts.update', $post), [
            'title' => 'Updated title', 'rating' => 'sfw', 'tags' => implode(', ', $names),
        ])->assertSessionHasNoErrors()->assertRedirect();
        $this->assertSame(15, $post->tags()->count());
    }

    public function test_empty_post_tag_proposal_can_clear_tags(): void
    {
        $post = Post::create(['type' => 'image', 'rating' => 'sfw']);
        Tag::syncFor($post, ['watercolor']);
        $this->actingAs($this->moderator())->post($this->url('propose', 'post', $post->id), [
            'tags' => [], 'base_version' => $post->fresh()->tag_version, 'summary' => 'Remove inaccurate tags.',
        ])->assertSessionHasNoErrors();
        $this->assertSame(0, $post->tags()->count());
    }

    public function test_owner_edit_cannot_overwrite_tags_changed_since_form_opened(): void
    {
        $owner = $this->member();
        $post = Post::create(['user_id' => $owner->id, 'type' => 'image', 'rating' => 'sfw', 'title' => 'Original title']);
        Tag::syncFor($post, ['watercolor']);
        $base = $post->fresh()->tag_version;
        Tag::syncFor($post, ['watercolor', 'portrait']);
        $this->actingAs($owner)->patch(route('posts.update', $post), [
            'title' => 'Stale title', 'rating' => 'sfw', 'tags' => 'watercolor',
            'base_tag_version' => $base,
        ])->assertSessionHasErrors('base_tag_version');
        $this->assertSame(['portrait', 'watercolor'], $post->tags()->pluck('name')->all());
        $this->assertSame('Original title', $post->fresh()->title);
    }

    public function test_standalone_wiki_edit_is_reviewed_and_can_be_reverted(): void
    {
        $wiki = WikiPage::create(['title' => 'Tagging guide', 'body' => 'Original guidance.']);
        $this->actingAs($this->member())->post($this->url('propose', 'wiki', $wiki->id), [
            'title' => $wiki->title, 'body' => 'Improved guidance.', 'rating' => 'sfw',
            'base_version' => 0, 'summary' => 'Improve the guide.',
        ])->assertSessionHasNoErrors();
        $this->assertSame('Original guidance.', $wiki->fresh()->body);
        $revision = KnowledgeRevision::first();
        $this->approve($revision);
        $this->assertSame('Improved guidance.', $wiki->fresh()->body);
        $this->post(route('admin.knowledge.review', $revision), [
            'action' => 'revert', 'reason' => 'Restore original guidance.',
        ])->assertSessionHasNoErrors();
        $this->assertSame('Original guidance.', $wiki->fresh()->body);
    }

    public function test_tag_implication_limit_is_exactly_fifty(): void
    {
        $root = $this->tag();
        foreach (range(1, 49) as $i) {
            $tag = $this->tag('implied '.$i);
            DB::table('tag_relations')->insert(['tag_id' => $root->id, 'related_tag_id' => $tag->id, 'kind' => 'implies']);
        }
        $this->assertCount(50, Tag::expandedNames([$root->name]));
        $extra = $this->tag('one too many');
        DB::table('tag_relations')->insert(['tag_id' => $root->id, 'related_tag_id' => $extra->id, 'kind' => 'implies']);
        $this->expectException(ValidationException::class);
        Tag::expandedNames([$root->name]);
    }

    public function test_starter_definitions_are_idempotent_and_do_not_overwrite_edits(): void
    {
        $tag = $this->tag();
        $tag->update(['wiki' => 'Community-maintained definition.']);
        $this->seed(KnowledgeSeeder::class);
        $count = Tag::count();
        $this->seed(KnowledgeSeeder::class);
        $this->assertSame($count, Tag::count());
        $this->assertSame('Community-maintained definition.', $tag->fresh()->wiki);
        $this->assertDatabaseCount('wiki_pages', 2);
    }
}
