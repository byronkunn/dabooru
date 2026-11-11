<?php

namespace Tests\Feature;

use App\Models\Post;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class PostViewTrackingTest extends TestCase
{
    use RefreshDatabase;

    public function test_views_require_delay_and_exclude_owner_and_bots(): void
    {
        $this->withCredentials();
        [$owner, $viewer] = User::factory()->count(2)->create();
        $post = Post::create(['user_id' => $owner->id, 'type' => 'image', 'rating' => 'sfw', 'is_original' => true]);
        $this->actingAs($viewer);
        $token = $this->get(route('posts.show', $post))->viewData('page')['props']['viewToken'];
        $this->assertSame(0, $post->fresh()->views);
        $this->postJson(route('posts.track', $post), ['token' => $token, 'event' => 'view'])->assertJsonPath('recorded', false);
        $this->travel(2)->seconds();
        $this->postJson(route('posts.track', $post), ['token' => $token, 'event' => 'view'])->assertJsonPath('recorded', true);
        $this->postJson(route('posts.track', $post), ['token' => $token, 'event' => 'view'])->assertJsonPath('recorded', false);
        $this->assertSame(1, $post->fresh()->views);
        $this->actingAs($owner)->postJson(route('posts.track', $post), ['token' => $token, 'event' => 'view'])->assertJsonPath('recorded', false);
        $this->actingAs($viewer)->withHeader('User-Agent', 'ExampleBot')->postJson(route('posts.track', $post), ['token' => $token, 'event' => 'view'])->assertJsonPath('recorded', false);
        $other = Post::create(['user_id' => $owner->id, 'type' => 'image', 'rating' => 'sfw']);
        $this->postJson(route('posts.track', $other), ['token' => $token, 'event' => 'view'])->assertStatus(422);
    }

    public function test_video_plays_are_separate_and_deduplicated(): void
    {
        $this->withCredentials();
        [$owner, $viewer] = User::factory()->count(2)->create();
        $post = Post::create(['user_id' => $owner->id, 'type' => 'video', 'rating' => 'sfw', 'is_original' => true]);
        $token = $this->actingAs($viewer)->get(route('posts.show', $post))->viewData('page')['props']['viewToken'];
        $this->postJson(route('posts.track', $post), ['token' => $token, 'event' => 'play'])->assertJsonPath('recorded', true);
        $this->postJson(route('posts.track', $post), ['token' => $token, 'event' => 'play'])->assertJsonPath('recorded', false);
        $this->assertSame(0, $post->fresh()->views);
        $this->assertDatabaseCount('video_plays', 1);
    }
}
