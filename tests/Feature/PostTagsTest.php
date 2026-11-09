<?php

namespace Tests\Feature;

use App\Models\KnowledgeRevision;
use App\Models\Post;
use App\Models\Tag;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class PostTagsTest extends TestCase
{
    use RefreshDatabase;

    public function test_media_view_includes_all_tags_and_contributor_permissions(): void
    {
        $owner = User::factory()->create();
        $post = Post::create(['user_id' => $owner->id, 'type' => 'image', 'rating' => 'sfw']);
        $names = array_map(fn ($number) => 'tag '.$number, range(1, 25));
        Tag::syncFor($post, $names);
        $this->get(route('posts.show', $post))->assertOk()->assertInertia(fn ($page) => $page
            ->has('post.tags', 25)->where('tagEditing.version', 1)->where('tagEditing.canContribute', false));
        $this->actingAs($owner)->get(route('posts.show', $post))->assertInertia(fn ($page) => $page
            ->where('tagEditing.canContribute', true)->where('tagEditing.trusted', false));
        $owner->forceFill(['contributions_restricted' => true])->save();
        $this->get(route('posts.show', $post))->assertInertia(fn ($page) => $page->where('tagEditing.canContribute', false));
    }

    public function test_tag_additions_preserve_existing_tags_and_use_moderation_and_version_checks(): void
    {
        $owner = User::factory()->create();
        $member = User::factory()->create();
        $post = Post::create(['user_id' => $owner->id, 'type' => 'image', 'rating' => 'sfw']);
        Tag::syncFor($post, ['existing']);
        $url = route('knowledge.propose', ['type' => 'post', 'id' => $post->id]);
        $payload = ['tags' => ['existing', 'new tag'], 'base_version' => 1, 'summary' => 'Add the missing subject tag.'];
        $this->actingAs($member)->from(route('posts.show', $post))->post($url, $payload)
            ->assertSessionHasNoErrors()->assertRedirect(route('posts.show', $post));
        $revision = KnowledgeRevision::firstOrFail();
        $this->assertSame('pending', $revision->status);
        $this->assertSame(['existing', 'new tag'], $revision->after['tags']);
        $this->assertSame(['existing'], $post->tags()->pluck('name')->all());

        $owner->forceFill(['is_admin' => true])->save();
        $this->actingAs($owner)->post($url, $payload)->assertSessionHasNoErrors();
        $this->assertSame(['existing', 'new tag'], $post->fresh()->tags()->orderBy('name')->pluck('name')->all());
        $this->actingAs($member)->post($url, $payload)->assertSessionHasErrors('base_version');
    }
}
