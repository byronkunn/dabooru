<?php

namespace Tests\Feature;

use App\Models\Like;
use App\Models\Post;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class LikeCounterTest extends TestCase
{
    use RefreshDatabase;

    private function makePost(): Post
    {
        return Post::create(['user_id' => User::factory()->create()->id, 'type' => 'image', 'rating' => 'sfw', 'title' => 'Work']);
    }

    public function test_the_counter_matches_real_rows_after_liking(): void
    {
        $post = $this->makePost();
        $fan = User::factory()->create();

        $this->actingAs($fan)->post(route('posts.like', $post));

        $this->assertSame(1, $post->fresh()->likes_count);
        $this->assertSame($post->likes()->count(), $post->fresh()->likes_count);
    }

    public function test_unliking_never_drives_the_counter_negative(): void
    {
        $post = $this->makePost();
        $fan = User::factory()->create();

        $this->actingAs($fan)->post(route('posts.like', $post));
        $this->actingAs($fan)->delete(route('posts.unlike', $post));
        // a second unlike must be a no-op, not a decrement
        $this->actingAs($fan)->delete(route('posts.unlike', $post));

        $this->assertSame(0, $post->fresh()->likes_count);
        $this->assertGreaterThanOrEqual(0, $post->fresh()->likes_count);
    }

    public function test_liking_is_idempotent(): void
    {
        $post = $this->makePost();
        $fan = User::factory()->create();

        $this->actingAs($fan)->post(route('posts.like', $post));
        $this->actingAs($fan)->post(route('posts.like', $post));

        $this->assertSame(1, Like::where('post_id', $post->id)->count());
        $this->assertSame(1, $post->fresh()->likes_count);
    }

    public function test_a_stale_counter_is_repaired_by_the_next_like(): void
    {
        $post = $this->makePost();
        // simulate history: a counter that drifted away from the rows
        $post->forceFill(['likes_count' => -3])->save();

        $this->actingAs(User::factory()->create())->post(route('posts.like', $post));

        $this->assertSame(1, $post->fresh()->likes_count);
    }

    public function test_the_reconcile_command_repairs_drift(): void
    {
        $post = $this->makePost();
        Like::create(['user_id' => User::factory()->create()->id, 'post_id' => $post->id]);
        $post->forceFill(['likes_count' => 99])->save();

        $this->artisan('posts:reconcile-counters')->assertSuccessful();

        $this->assertSame(1, $post->fresh()->likes_count);
    }

    public function test_the_dry_run_does_not_write(): void
    {
        $post = $this->makePost();
        $post->forceFill(['likes_count' => 42])->save();

        $this->artisan('posts:reconcile-counters', ['--dry-run' => true])->assertSuccessful();

        $this->assertSame(42, $post->fresh()->likes_count);
    }
}
