<?php

namespace Tests\Feature;

use App\Models\KnowledgeRevision;
use App\Models\Post;
use App\Models\Tag;
use App\Models\User;
use App\Support\KnowledgeService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use Tests\TestCase;

class AdminTagsTest extends TestCase
{
    use RefreshDatabase;

    private function admin(): User
    {
        $admin = User::factory()->create();
        $admin->forceFill(['is_admin' => true])->save();

        return $admin;
    }

    public function test_tag_management_and_merges_are_administrator_only(): void
    {
        $source = Tag::create(['name' => 'source tag']);
        $destination = Tag::create(['name' => 'destination tag']);
        $input = ['destination_id' => $destination->id, 'base_version' => 0, 'destination_version' => 0, 'reason' => 'Duplicate tag.'];
        $this->get(route('admin.tags.index'))->assertRedirect(route('login'));
        $user = User::factory()->create();
        $this->actingAs($user)->get(route('admin.tags.index'))->assertForbidden();
        $this->post(route('admin.tags.merge', $source), $input)->assertForbidden();
        $user->forceFill(['staff_roles' => ['curator']])->save();
        $this->get(route('admin.tags.index'))->assertForbidden();
        $this->post(route('admin.tags.merge', $source), $input)->assertForbidden();
        $this->actingAs($this->admin())->get(route('admin.tags.index'))->assertInertia(fn ($page) => $page
            ->component('Admin/Tags')->has('tags.data', 2));
    }

    public function test_admin_merge_moves_all_media_and_followers_preserving_aliases_and_history(): void
    {
        $admin = $this->admin();
        $source = Tag::create(['name' => 'seated duplicate']);
        $destination = Tag::create(['name' => 'seated', 'wiki' => 'Keep this definition.']);
        $implied = Tag::create(['name' => 'pose']);
        DB::table('tag_relations')->insert(['tag_id' => $destination->id, 'related_tag_id' => $implied->id, 'kind' => 'implies']);
        DB::table('tag_aliases')->insert(['tag_id' => $source->id, 'name' => 'old sitting name']);
        DB::table('tag_user')->insert([
            ['tag_id' => $source->id, 'user_id' => $admin->id],
            ['tag_id' => $destination->id, 'user_id' => $admin->id],
            ['tag_id' => $source->id, 'user_id' => User::factory()->create()->id],
        ]);
        $posts = [];
        foreach (Post::TYPES as $type) {
            $post = Post::create(['user_id' => $admin->id, 'type' => $type, 'rating' => 'sfw']);
            Tag::syncFor($post, [$source->name, $destination->name, 'other label'], audit: false, expand: false);
            $posts[] = $post;
        }
        $posts[1]->delete();
        $posts[2]->update(['is_draft' => true]);
        $this->actingAs($admin)->post(route('admin.tags.merge', $source), [
            'destination_id' => $destination->id, 'base_version' => 0, 'destination_version' => 0,
            'reason' => 'Combine duplicate position tags.',
        ])->assertSessionHasNoErrors()->assertRedirect(route('admin.tags.index'));
        foreach ($posts as $post) {
            $this->assertSame(['other label', 'pose', 'seated'], $post->tags()->orderBy('name')->pluck('name')->all());
            $this->assertSame(2, Post::withoutGlobalScope('published')->withTrashed()->findOrFail($post->id)->tag_version);
            $this->assertDatabaseHas('knowledge_revisions', ['target_type' => 'post', 'target_id' => $post->id, 'status' => 'accepted']);
        }
        $this->assertSame($destination->id, $source->fresh()->merged_into_id);
        $this->assertSame('Keep this definition.', $destination->fresh()->wiki);
        $this->assertSame('seated', Tag::canonicalName('old sitting name'));
        $this->assertSame('seated', Tag::canonicalName($source->name));
        $this->assertDatabaseCount('tag_user', 2);
        $this->assertDatabaseMissing('tag_user', ['tag_id' => $source->id]);
        $this->assertDatabaseHas('tag_user', ['tag_id' => $destination->id, 'user_id' => $admin->id]);
        $this->assertDatabaseHas('moderation_actions', ['action' => 'knowledge_merge']);
        foreach (KnowledgeRevision::where('summary', 'Combine duplicate position tags.')->get() as $revision) {
            try {
                KnowledgeService::revert($revision, $admin, 'Try reverting one part.');
                $this->fail('Merge revisions must not be individually reverted.');
            } catch (ValidationException $exception) {
                $this->assertArrayHasKey('revision', $exception->errors());
            }
        }
        $this->get(route('knowledge.show', ['type' => 'tag', 'id' => $source->id]))
            ->assertRedirect(route('knowledge.show', ['type' => 'tag', 'id' => $destination->id]));
        $this->post(route('admin.tags.merge', $source), [
            'destination_id' => $destination->id, 'base_version' => 1, 'destination_version' => 0, 'reason' => 'Repeat merge.',
        ])->assertSessionHasErrors('destination_id');
    }

    public function test_invalid_stale_or_related_merges_leave_media_unchanged(): void
    {
        $admin = $this->admin();
        $source = Tag::create(['name' => 'source']);
        $destination = Tag::create(['name' => 'destination']);
        $post = Post::create(['user_id' => $admin->id, 'type' => 'image', 'rating' => 'sfw']);
        Tag::syncFor($post, ['source'], audit: false);
        $input = ['destination_id' => $destination->id, 'base_version' => 0, 'destination_version' => 0, 'reason' => 'Merge duplicate.'];
        $source->update(['version' => 1]);
        $this->actingAs($admin)->post(route('admin.tags.merge', $source), $input)->assertSessionHasErrors('base_version');
        $input['base_version'] = 1;
        $this->post(route('admin.tags.merge', $source), [...$input, 'destination_version' => 1])->assertSessionHasErrors('base_version');
        DB::table('tag_relations')->insert(['tag_id' => $source->id, 'related_tag_id' => $destination->id, 'kind' => 'related']);
        $this->post(route('admin.tags.merge', $source), $input)->assertSessionHasErrors('destination_id');
        $this->assertSame([$source->name], $post->tags()->pluck('name')->all());
        $this->assertNull($source->fresh()->merged_into_id);
        $this->post(route('admin.tags.merge', $source), [...$input, 'destination_id' => $source->id])->assertSessionHasErrors('destination_id');
    }

    public function test_merge_rolls_back_all_media_when_destination_implications_exceed_fifty_tags(): void
    {
        $admin = $this->admin();
        $source = Tag::create(['name' => 'duplicate']);
        $destination = Tag::create(['name' => 'canonical']);
        $implied = Tag::create(['name' => 'implied']);
        DB::table('tag_relations')->insert(['tag_id' => $destination->id, 'related_tag_id' => $implied->id, 'kind' => 'implies']);
        $first = Post::create(['user_id' => $admin->id, 'type' => 'image', 'rating' => 'sfw']);
        $second = Post::create(['user_id' => $admin->id, 'type' => 'video', 'rating' => 'sfw']);
        Tag::syncFor($first, [$source->name], audit: false);
        Tag::syncFor($second, [$source->name, ...array_map(fn ($i) => "label {$i}", range(1, 49))], audit: false);
        $this->assertCount(50, $second->tags);
        $this->actingAs($admin)->post(route('admin.tags.merge', $source), [
            'destination_id' => $destination->id, 'base_version' => 0, 'destination_version' => 0,
            'reason' => 'Combine duplicate labels.',
        ])->assertSessionHasErrors('tags');
        $this->assertSame(['duplicate'], $first->tags()->pluck('name')->all());
        $this->assertSame(1, $first->fresh()->tag_version);
        $this->assertCount(50, $second->tags()->get());
        $this->assertNull($source->fresh()->merged_into_id);
        $this->assertDatabaseCount('knowledge_revisions', 0);
        $this->assertDatabaseCount('moderation_actions', 0);
    }

    public function test_admin_can_lock_unlock_edit_and_merge_unused_tags(): void
    {
        $admin = $this->admin();
        $source = Tag::create(['name' => 'empty source']);
        $destination = Tag::create(['name' => 'empty destination']);
        $this->actingAs($admin)->post(route('admin.knowledge.lock', ['type' => 'tag', 'id' => $source->id]), ['locked' => true])
            ->assertSessionHasNoErrors();
        $this->assertTrue($source->fresh()->locked);
        $this->post(route('admin.knowledge.lock', ['type' => 'tag', 'id' => $source->id]), ['locked' => false])
            ->assertSessionHasNoErrors();
        $this->assertFalse($source->fresh()->locked);
        $after = KnowledgeService::snapshot($source);
        $after['category'] = 'clothing';
        $after['wiki'] = 'Use for the depicted garment.';
        $after['aliases'] = ['empty alias'];
        $this->post(route('knowledge.propose', ['type' => 'tag', 'id' => $source->id]),
            [...$after, 'base_version' => 2, 'summary' => 'Categorize the garment.'])->assertSessionHasNoErrors();
        $this->assertSame('clothing', $source->fresh()->category);
        $this->assertSame('Use for the depicted garment.', $source->fresh()->wiki);
        $this->post(route('admin.tags.merge', $source), [
            'destination_id' => $destination->id, 'base_version' => 3, 'destination_version' => 0,
            'reason' => 'Combine unused duplicates.',
        ])->assertSessionHasNoErrors();
        $this->assertSame('empty destination', Tag::canonicalName('empty alias'));
        $this->assertDatabaseHas('moderation_actions', ['action' => 'knowledge_merge', 'target_id' => $source->id]);
    }
}
