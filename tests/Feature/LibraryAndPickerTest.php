<?php

namespace Tests\Feature;

use App\Models\Post;
use App\Models\Tag;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class LibraryAndPickerTest extends TestCase
{
    use RefreshDatabase;

    public function test_library_landing_page_shows_recently_saved_works(): void
    {
        $user = User::factory()->create();
        $artist = User::factory()->create(['is_unsorted' => false]);
        $post = Post::create(['user_id' => $artist->id, 'type' => 'image', 'rating' => 'sfw', 'title' => 'Saved work']);

        $this->actingAs($user)->post(route('posts.save', $post));
        $this->actingAs($user)->post(route('posts.save', $post)); // idempotent

        $this->actingAs($user)->get(route('collections.index'))
            ->assertOk()
            ->assertInertia(fn ($page) => $page
                ->component('Collections/Index')
                ->has('recent', 1)
                ->where('recent.0.id', $post->id)
                ->where('allSaved.count', 1));
    }

    public function test_library_landing_page_is_empty_but_valid_for_a_new_reader(): void
    {
        $user = User::factory()->create();

        $this->actingAs($user)->get(route('collections.index'))
            ->assertOk()
            ->assertInertia(fn ($page) => $page->has('recent', 0)->where('allSaved.count', 0));
    }

    public function test_muted_tags_keep_works_out_of_recently_saved(): void
    {
        $user = User::factory()->create();
        $artist = User::factory()->create(['is_unsorted' => false]);
        $post = Post::create(['user_id' => $artist->id, 'type' => 'image', 'rating' => 'sfw', 'title' => 'Hidden']);
        Tag::syncFor($post, ['watercolor']);
        $this->actingAs($user)->post(route('posts.save', $post));
        \App\Support\CollectionLibrary::addMute($user, 'watercolor');

        $this->actingAs($user)->get(route('collections.index'))
            ->assertOk()
            ->assertInertia(fn ($page) => $page->has('recent', 0));
    }

    public function test_the_sticker_picker_redirects_a_page_visit(): void
    {
        $user = User::factory()->create();

        $this->actingAs($user)->get(route('messages.stickers.picker'))
            ->assertRedirect(route('messages.stickers.index'));
    }

    public function test_the_sticker_picker_still_returns_json_for_the_chat_ui(): void
    {
        $user = User::factory()->create();

        $this->actingAs($user)->getJson(route('messages.stickers.picker'))
            ->assertOk()
            ->assertJson([]);
    }
}
