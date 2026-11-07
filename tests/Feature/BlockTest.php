<?php

namespace Tests\Feature;

use App\Models\Block;
use App\Models\Follow;
use App\Models\Post;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class BlockTest extends TestCase
{
    use RefreshDatabase;

    public function test_blocking_hides_their_work_from_the_gallery(): void
    {
        $me = User::factory()->create();
        $them = User::factory()->create();
        Post::create(['user_id' => $them->id, 'type' => 'image', 'rating' => 'sfw', 'title' => 'Their work']);
        Post::create(['user_id' => $me->id, 'type' => 'image', 'rating' => 'sfw', 'title' => 'My work']);
        Post::create(['user_id' => null, 'type' => 'image', 'rating' => 'sfw', 'title' => 'Unsorted']);

        $this->actingAs($me)->post(route('users.block', $them->username))->assertRedirect();

        $titles = collect($this->actingAs($me)->get('/')->viewData('page')['props']['posts'])->pluck('title');
        $this->assertNotContains('Their work', $titles);
        $this->assertContains('My work', $titles);
        $this->assertContains('Unsorted', $titles);
    }

    public function test_blocking_is_mutual_for_visibility(): void
    {
        $me = User::factory()->create();
        $them = User::factory()->create();
        Post::create(['user_id' => $them->id, 'type' => 'image', 'rating' => 'sfw', 'title' => 'Their work']);

        // they block me — I should not see their work either
        Block::create(['blocker_id' => $them->id, 'blocked_id' => $me->id]);

        $titles = collect($this->actingAs($me)->get('/')->viewData('page')['props']['posts'])->pluck('title');
        $this->assertNotContains('Their work', $titles);
    }

    public function test_blocking_ends_following_either_way(): void
    {
        $me = User::factory()->create();
        $them = User::factory()->create();
        Follow::create(['follower_id' => $me->id, 'following_id' => $them->id]);
        Follow::create(['follower_id' => $them->id, 'following_id' => $me->id]);

        $this->actingAs($me)->post(route('users.block', $them->username))->assertRedirect();

        $this->assertSame(0, Follow::count());
    }

    public function test_blocked_people_do_not_appear_in_the_following_feed(): void
    {
        $me = User::factory()->create();
        $them = User::factory()->create();
        Follow::create(['follower_id' => $me->id, 'following_id' => $them->id]);
        Post::create(['user_id' => $them->id, 'type' => 'image', 'rating' => 'sfw', 'title' => 'Their work']);

        $this->actingAs($me)->post(route('users.block', $them->username));

        $this->actingAs($me)->get('/following')->assertInertia(fn ($page) => $page->has('posts', 0));
    }

    public function test_unblocking_brings_them_back(): void
    {
        $me = User::factory()->create();
        $them = User::factory()->create();
        Post::create(['user_id' => $them->id, 'type' => 'image', 'rating' => 'sfw', 'title' => 'Their work']);

        $this->actingAs($me)->post(route('users.block', $them->username));
        $this->actingAs($me)->delete(route('users.unblock', $them->username))->assertRedirect();

        $titles = collect($this->actingAs($me)->get('/')->viewData('page')['props']['posts'])->pluck('title');
        $this->assertContains('Their work', $titles);
    }

    public function test_you_cannot_block_yourself(): void
    {
        $me = User::factory()->create();
        $this->actingAs($me)->post(route('users.block', $me->username))->assertStatus(422);
        $this->assertSame(0, Block::count());
    }

    public function test_blocked_list_page_shows_them(): void
    {
        $me = User::factory()->create();
        $them = User::factory()->create(['username' => 'themb']);
        Block::create(['blocker_id' => $me->id, 'blocked_id' => $them->id]);

        $this->actingAs($me)->get(route('blocks.index'))->assertOk()
            ->assertInertia(fn ($page) => $page->component('Settings/Blocked')->has('blocks', 1)
                ->where('blocks.0.user.username', 'themb'));
    }
}
