<?php

namespace Tests\Feature;

use App\Models\Block;
use App\Models\CuratedShelf;
use App\Models\Follow;
use App\Models\Like;
use App\Models\Post;
use App\Models\Tag;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class DiscoverTest extends TestCase
{
    use RefreshDatabase;

    private function work(User $user, array $attributes = [], array $tags = []): Post
    {
        $post = Post::create($attributes + [
            'user_id' => $user->id, 'type' => 'image', 'rating' => 'sfw', 'is_original' => true,
        ]);
        if ($tags) {
            Tag::syncFor($post, $tags);
        }

        return $post;
    }

    private function likes(Post $post, int $count, int $hoursAgo): void
    {
        foreach (User::factory()->count($count)->create() as $user) {
            $like = new Like(['user_id' => $user->id, 'post_id' => $post->id]);
            $like->created_at = now()->subHours($hoursAgo);
            $like->save();
        }
    }

    private function ids(): array
    {
        $response = $this->get(route('discover'))->assertOk();

        return $response->viewData('page')['props']['posts'];
    }

    public function test_followed_users_and_tags_and_liked_work_drive_personal_recommendations(): void
    {
        $this->freezeTime();
        [$viewer, $author, $other] = User::factory()->count(3)->create();
        $seed = $this->work($author, [], ['watercolor']);
        Like::create(['user_id' => $viewer->id, 'post_id' => $seed->id]);
        $likedAuthor = $this->work($author);
        $unrelatedUpload = $this->work($author, ['artist_name' => 'Unrelated credit', 'is_original' => false]);
        $likedTags = $this->work($other, [], ['watercolor']);
        $followedTag = $this->work($other, [], ['landscape']);
        $viewer->followedTags()->attach(Tag::where('name', 'landscape')->firstOrFail());
        Follow::create(['follower_id' => $viewer->id, 'following_id' => $other->id]);
        $followedUser = $this->work($other);
        $neutral = $this->work(User::factory()->create());

        $this->actingAs($viewer);
        $posts = collect($this->ids())->keyBy('id');
        $this->assertArrayNotHasKey($seed->id, $posts->all());
        $this->assertContains('Similar to work you liked', $posts[$likedAuthor->id]['recommendation_reasons']);
        $this->assertNotContains('Similar to work you liked', $posts[$unrelatedUpload->id]['recommendation_reasons']);
        $this->assertContains('Similar to work you liked', $posts[$likedTags->id]['recommendation_reasons']);
        $this->assertContains('Matches tags you follow', $posts[$followedTag->id]['recommendation_reasons']);
        $this->assertContains('From a user you follow', $posts[$followedUser->id]['recommendation_reasons']);
        $this->assertLessThan(
            array_search($neutral->id, $posts->keys()->all()),
            array_search($likedAuthor->id, $posts->keys()->all())
        );
        $this->get(route('discover'))->assertInertia(fn ($page) => $page->where('personalized', true));

        $this->delete(route('tags.unfollow'), ['tag' => 'landscape']);
        $this->delete(route('users.unfollow', $other->username));
        $this->delete(route('posts.unlike', $seed));
        $this->get(route('discover'))->assertInertia(fn ($page) => $page->where('personalized', false));
    }

    public function test_guest_and_new_account_see_recent_popularity_and_growth_not_lifetime_totals(): void
    {
        $this->freezeTime();
        $author = User::factory()->create();
        $old = $this->work($author, ['created_at' => now()->subYear(), 'likes_count' => 99999]);
        $rising = $this->work($author);
        $slowing = $this->work($author);
        $this->likes($rising, 4, 1);
        $this->likes($slowing, 4, 1);
        $this->likes($slowing, 6, 30);
        $posts = $this->ids();
        $this->assertSame([$rising->id, $slowing->id, $old->id], array_column($posts, 'id'));
        $this->assertContains('Gaining popularity', $posts[0]['recommendation_reasons']);
        $this->assertContains('Popular this week', $posts[1]['recommendation_reasons']);
        $this->assertNotContains('Gaining popularity', $posts[1]['recommendation_reasons']);
        $this->actingAs(User::factory()->create());
        $this->assertSame(array_column($posts, 'id'), array_column($this->ids(), 'id'));
        $this->get(route('discover'))->assertInertia(fn ($page) => $page->where('personalized', false));
    }

    public function test_hidden_work_and_hidden_likes_never_influence_discovery(): void
    {
        [$viewer, $author, $blocked, $blocking] = User::factory()->count(4)->create();
        $viewer->update(['show_explicit' => false]);
        Block::create(['blocker_id' => $viewer->id, 'blocked_id' => $blocked->id]);
        Block::create(['blocker_id' => $blocking->id, 'blocked_id' => $viewer->id]);
        $this->work($viewer);
        $adult = $this->work($author, ['rating' => 'explicit'], ['hidden-interest']);
        $blockedWork = $this->work($blocked);
        $this->work($blocking);
        $this->work($author, ['is_draft' => true]);
        $this->work($author)->delete();
        Like::create(['user_id' => $viewer->id, 'post_id' => $adult->id]);
        Like::create(['user_id' => $viewer->id, 'post_id' => $blockedWork->id]);
        $safe = $this->work($author, [], ['hidden-interest']);

        $this->actingAs($viewer);
        $posts = $this->ids();
        $this->assertSame([$safe->id], array_column($posts, 'id'));
        $this->assertSame(['Something new to explore'], $posts[0]['recommendation_reasons']);
        $this->get(route('discover'))->assertInertia(fn ($page) => $page->where('personalized', false));
    }

    public function test_external_artist_affinity_does_not_misidentify_the_uploader_as_the_artist(): void
    {
        [$viewer, $uploader, $other] = User::factory()->count(3)->create();
        $seed = $this->work($uploader, ['artist_name' => 'Outside Artist', 'is_original' => false]);
        Like::create(['user_id' => $viewer->id, 'post_id' => $seed->id]);
        $match = $this->work($other, ['artist_name' => 'Outside Artist', 'is_original' => false]);
        $unrelated = $this->work($uploader);
        $this->actingAs($viewer);
        $posts = $this->ids();
        $this->assertSame([$match->id, $unrelated->id], array_column($posts, 'id'));
        $this->assertSame(['Something new to explore'], $posts[1]['recommendation_reasons']);
    }

    public function test_pagination_is_deterministic_without_duplicates_and_ignores_stale_likes(): void
    {
        $this->freezeTime();
        [$viewer, $author] = User::factory()->count(2)->create();
        $seed = $this->work($author);
        $like = new Like(['user_id' => $viewer->id, 'post_id' => $seed->id]);
        $like->created_at = now()->subDays(91);
        $like->save();
        for ($i = 0; $i < 25; $i++) {
            $this->work($author);
        }
        $this->actingAs($viewer)->get(route('discover'))->assertInertia(fn ($page) => $page
            ->where('personalized', false)->has('posts', 24));
        $first = $this->ids();
        $secondResponse = $this->get(route('discover', ['page' => 2]))->assertOk();
        $second = $secondResponse->viewData('page')['props']['posts'];
        $this->assertCount(1, $second);
        $this->assertEmpty(array_intersect(array_column($first, 'id'), array_column($second, 'id')));
    }

    public function test_weekly_popularity_expires_and_tag_affinity_is_capped_at_three_matches(): void
    {
        $this->freezeTime();
        [$viewer, $author] = User::factory()->count(2)->create();
        $popular = $this->work($author);
        $expired = $this->work($author);
        $this->likes($popular, 3, 72);
        $this->likes($expired, 3, 8 * 24);
        $posts = $this->ids();
        $this->assertSame([$popular->id, $expired->id], array_column($posts, 'id'));
        $this->assertContains('Popular this week', $posts[0]['recommendation_reasons']);
        $this->assertSame(['Something new to explore'], $posts[1]['recommendation_reasons']);

        $overTagged = $this->work($author, [], ['one', 'two', 'three', 'four']);
        $threeTags = $this->work($author, [], ['one', 'two', 'three']);
        $viewer->followedTags()->attach(Tag::whereIn('name', ['one', 'two', 'three', 'four'])->pluck('id'));
        $this->actingAs($viewer);
        $posts = $this->ids();
        $this->assertSame([$threeTags->id, $overTagged->id], array_slice(array_column($posts, 'id'), 0, 2));
    }

    public function test_staff_picks_only_publish_visible_items_from_published_shelves(): void
    {
        [$viewer, $artist, $blocked] = User::factory()->count(3)->create();
        $visible = $this->work($artist, ['title' => 'Featured work']);
        $explicit = $this->work($artist, ['rating' => 'explicit']);
        $blockedWork = $this->work($blocked);
        Block::create(['blocker_id' => $viewer->id, 'blocked_id' => $blocked->id]);
        $entries = collect([$visible, $explicit, $blockedWork])->map(fn (Post $work) => [
            'kind' => 'work', 'id' => $work->id,
        ])->all();
        CuratedShelf::create(['title' => 'Staff selection', 'published' => true, 'entries' => $entries]);
        CuratedShelf::create(['title' => 'Private selection', 'published' => false, 'entries' => $entries]);

        $this->actingAs($viewer)->get(route('discover'))->assertInertia(fn ($page) => $page
            ->has('staffPicks', 1)->where('staffPicks.0.title', 'Staff selection')
            ->has('staffPicks.0.items', 1)->where('staffPicks.0.items.0.url', route('posts.show', $visible, false)));
    }
}
