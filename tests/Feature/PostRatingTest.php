<?php

namespace Tests\Feature;

use App\Models\Block;
use App\Models\Post;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class PostRatingTest extends TestCase
{
    use RefreshDatabase;

    private function work(): Post
    {
        return Post::create([
            'user_id' => User::factory()->create()->id,
            'type' => 'image', 'rating' => 'sfw', 'title' => 'Rating test work',
        ]);
    }

    public function test_signed_in_users_can_rate_and_change_their_rating(): void
    {
        $post = $this->work();
        $user = User::factory()->create();
        $url = route('posts.stars', $post);
        $this->actingAs($user)->from(route('posts.show', $post))->put($url, ['stars' => 5])->assertRedirect(route('posts.show', $post));
        $this->put($url, ['stars' => 5])->assertRedirect();
        $this->put($url, ['stars' => 2])->assertRedirect();
        $this->assertDatabaseCount('post_ratings', 1);
        $this->assertDatabaseHas('post_ratings', ['post_id' => $post->id, 'user_id' => $user->id, 'stars' => 2]);
        $this->get(route('posts.show', $post))->assertInertia(fn ($page) => $page
            ->where('starRating.average', 2)->where('starRating.count', 1)
            ->where('starRating.mine', 2)->where('starRating.canRate', true));
        $this->assertSame(0, $post->fresh()->likes_count);
        $this->assertSame('sfw', $post->fresh()->rating);
    }

    public function test_average_and_count_include_each_user_once(): void
    {
        $post = $this->work();
        $a = User::factory()->create();
        $b = User::factory()->create();
        $this->actingAs($a)->put(route('posts.stars', $post), ['stars' => 4]);
        $this->actingAs($b)->put(route('posts.stars', $post), ['stars' => 5]);
        $this->get(route('posts.show', $post))->assertInertia(fn ($page) => $page
            ->where('starRating.average', 4.5)->where('starRating.count', 2)->where('starRating.mine', 5));
    }

    public function test_guests_see_the_summary_but_cannot_rate(): void
    {
        $post = $this->work();
        $this->put(route('posts.stars', $post), ['stars' => 5])->assertRedirect(route('login'));
        $this->get(route('posts.show', $post))->assertInertia(fn ($page) => $page
            ->where('starRating.average', null)->where('starRating.count', 0)
            ->where('starRating.mine', null)->where('starRating.canRate', false));
        $this->assertDatabaseCount('post_ratings', 0);
    }

    public function test_only_whole_numbers_from_one_to_five_are_accepted(): void
    {
        $post = $this->work();
        $this->actingAs(User::factory()->create());
        foreach ([null, 0, 6, -1, 2.5, 'bad', [5]] as $stars) {
            $this->put(route('posts.stars', $post), ['stars' => $stars])->assertSessionHasErrors('stars');
        }
        $this->assertDatabaseCount('post_ratings', 0);
    }

    public function test_banned_and_blocked_users_cannot_rate(): void
    {
        $post = $this->work();
        $banned = User::factory()->create(['banned_at' => now()]);
        $this->actingAs($banned)->put(route('posts.stars', $post), ['stars' => 5])->assertForbidden();
        $user = User::factory()->create();
        foreach ([[$user->id, $post->user_id], [$post->user_id, $user->id]] as [$blocker, $blocked]) {
            $block = Block::create(['blocker_id' => $blocker, 'blocked_id' => $blocked]);
            $this->actingAs($user)->put(route('posts.stars', $post), ['stars' => 5])->assertForbidden();
            $this->get(route('posts.show', $post))->assertInertia(fn ($page) => $page->where('starRating.canRate', false));
            $block->delete();
        }
        $this->assertDatabaseCount('post_ratings', 0);
    }

    public function test_deleted_and_draft_posts_cannot_be_rated(): void
    {
        $post = $this->work();
        $this->actingAs(User::factory()->create());
        $post->delete();
        $this->put(route('posts.stars', $post), ['stars' => 5])->assertNotFound();
        $draft = Post::create(['type' => 'image', 'rating' => 'sfw', 'is_draft' => true]);
        $this->put(route('posts.stars', $draft), ['stars' => 5])->assertNotFound();
        $this->assertDatabaseCount('post_ratings', 0);
    }

    public function test_ratings_are_removed_when_the_user_or_post_is_permanently_deleted(): void
    {
        $post = $this->work();
        $user = User::factory()->create();
        $post->starRatings()->create(['user_id' => $user->id, 'stars' => 5]);
        $user->delete();
        $this->assertDatabaseCount('post_ratings', 0);
        $post->starRatings()->create(['user_id' => User::factory()->create()->id, 'stars' => 3]);
        $post->forceDelete();
        $this->assertDatabaseCount('post_ratings', 0);
    }
}
