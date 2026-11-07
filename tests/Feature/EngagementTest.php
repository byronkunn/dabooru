<?php

namespace Tests\Feature;

use App\Models\Follow;
use App\Models\Post;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class EngagementTest extends TestCase
{
    use RefreshDatabase;

    public function test_a_user_can_like_and_unlike_a_post(): void
    {
        $artist = User::factory()->create();
        $fan = User::factory()->create();
        $post = Post::create(['user_id' => $artist->id, 'type' => 'image', 'rating' => 'sfw']);

        $this->actingAs($fan)->post(route('posts.like', $post))->assertRedirect();
        $this->assertDatabaseHas('likes', ['user_id' => $fan->id, 'post_id' => $post->id]);
        $this->assertSame(1, $post->fresh()->likes_count);

        // liking twice does not double count
        $this->actingAs($fan)->post(route('posts.like', $post));
        $this->assertSame(1, $post->fresh()->likes_count);

        $this->actingAs($fan)->delete(route('posts.unlike', $post))->assertRedirect();
        $this->assertSame(0, $post->fresh()->likes_count);
    }

    public function test_post_view_is_counted_once_per_viewer_per_day(): void
    {
        $post = Post::create(['user_id' => User::factory()->create()->id, 'type' => 'image', 'rating' => 'sfw']);

        $this->get('/p/'.$post->id)->assertOk();
        $this->get('/p/'.$post->id)->assertOk();

        $this->assertSame(1, $post->fresh()->views);
        $this->assertDatabaseCount('post_views', 1);
    }

    public function test_follow_and_unfollow(): void
    {
        $artist = User::factory()->create();
        $fan = User::factory()->create();

        $this->actingAs($fan)->post(route('users.follow', $artist))->assertRedirect();
        $this->assertDatabaseHas('follows', ['follower_id' => $fan->id, 'following_id' => $artist->id]);

        $this->actingAs($fan)->delete(route('users.unfollow', $artist))->assertRedirect();
        $this->assertDatabaseMissing('follows', ['follower_id' => $fan->id, 'following_id' => $artist->id]);
    }

    public function test_cannot_follow_yourself(): void
    {
        $user = User::factory()->create();

        $this->actingAs($user)->post(route('users.follow', $user))->assertStatus(422);
        $this->assertSame(0, Follow::count());
    }

    public function test_commenting_increments_the_counter_and_notifies_the_artist(): void
    {
        $artist = User::factory()->create();
        $fan = User::factory()->create();
        $post = Post::create(['user_id' => $artist->id, 'type' => 'image', 'rating' => 'sfw']);

        $this->actingAs($fan)->post(route('comments.store', $post), ['body' => 'Beautiful work'])->assertRedirect();

        $this->assertSame(1, $post->fresh()->comments_count);
        $this->assertDatabaseHas('notifications_app', ['user_id' => $artist->id, 'actor_id' => $fan->id, 'type' => 'comment']);
    }

    public function test_liking_your_own_post_does_not_notify(): void
    {
        $artist = User::factory()->create();
        $post = Post::create(['user_id' => $artist->id, 'type' => 'image', 'rating' => 'sfw']);

        $this->actingAs($artist)->post(route('posts.like', $post));
        $this->assertDatabaseCount('notifications_app', 0);
    }
}
