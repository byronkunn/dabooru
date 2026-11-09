<?php

namespace Tests\Feature;

use App\Models\Block;
use App\Models\Follow;
use App\Models\Post;
use App\Models\Tag;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class FollowingTagsTest extends TestCase
{
    use RefreshDatabase;

    public function test_tag_follows_are_authenticated_private_idempotent_and_resolve_aliases(): void
    {
        $tag = Tag::create(['name' => 'sketch']);
        DB::table('tag_aliases')->insert(['name' => 'sketches', 'tag_id' => $tag->id]);
        $viewer = User::factory()->create();
        $other = User::factory()->create();
        $this->post(route('tags.follow'), ['tag' => 'sketch'])->assertRedirect(route('login'));
        $this->actingAs($viewer)->post(route('tags.follow'), ['tag' => ' Sketches '])->assertRedirect();
        $this->post(route('tags.follow'), ['tag' => 'sketch'])->assertRedirect();
        $this->assertDatabaseCount('tag_user', 1);
        $this->get(route('following'))->assertInertia(fn ($page) => $page
            ->where('auth.followed_tags.0.name', 'sketch')->has('auth.followed_tags', 1));
        $this->actingAs($other)->get(route('following'))->assertInertia(fn ($page) => $page->has('auth.followed_tags', 0));
        $this->delete(route('tags.unfollow'), ['tag' => 'sketch'])->assertRedirect();
        $this->assertDatabaseCount('tag_user', 1);
        $this->actingAs($viewer)->delete(route('tags.unfollow'), ['tag' => 'sketches'])->assertRedirect();
        $this->delete(route('tags.unfollow'), ['tag' => 'sketch'])->assertRedirect();
        $this->assertDatabaseCount('tag_user', 0);
        $this->post(route('tags.follow'), ['tag' => 'unknown'])->assertNotFound();
        $this->post(route('tags.follow'), ['tag' => []])->assertSessionHasErrors('tag');
    }

    public function test_feed_combines_users_and_tags_without_duplicates_and_respects_visibility(): void
    {
        [$viewer, $author, $other, $blocked, $blocking] = User::factory()->count(5)->create();
        $viewer->update(['show_explicit' => false]);
        $tag = Tag::create(['name' => 'sketch']);
        $viewer->followedTags()->attach($tag);
        Follow::create(['follower_id' => $viewer->id, 'following_id' => $author->id]);
        Block::create(['blocker_id' => $viewer->id, 'blocked_id' => $blocked->id]);
        Block::create(['blocker_id' => $blocking->id, 'blocked_id' => $viewer->id]);
        $make = function (User $uploader, array $attributes = [], bool $tagged = true) use ($tag): Post {
            $post = Post::create($attributes + ['user_id' => $uploader->id, 'type' => 'image', 'rating' => 'sfw']);
            if ($tagged) {
                $post->tags()->attach($tag);
            }

            return $post;
        };
        $both = $make($author);
        $byTag = $make($other);
        $byUser = $make($author, [], false);
        $make($other, [], false);
        $make($other, ['rating' => 'explicit']);
        $make($other, ['is_draft' => true]);
        $make($other)->delete();
        $make($blocked);
        $make($blocking);

        $response = $this->actingAs($viewer)->getJson(route('following'))->assertOk()->assertJsonCount(3, 'posts');
        $this->assertEqualsCanonicalizing([$both->id, $byTag->id, $byUser->id], $response->json('posts.*.id'));
        $this->delete(route('tags.unfollow'), ['tag' => 'sketch'])->assertRedirect();
        $this->getJson(route('following'))->assertJsonCount(2, 'posts');
        $this->delete(route('users.unfollow', $author->username))->assertRedirect();
        $this->getJson(route('following'))->assertJsonCount(0, 'posts');
        $this->assertDatabaseMissing('follows', ['follower_id' => $viewer->id, 'following_id' => $author->id]);
    }

    public function test_following_management_and_feed_paginate_independently(): void
    {
        $viewer = User::factory()->create();
        $authors = User::factory()->count(21)->create();
        foreach ($authors as $author) {
            Follow::create(['follower_id' => $viewer->id, 'following_id' => $author->id]);
            Post::create(['user_id' => $author->id, 'type' => 'image', 'rating' => 'sfw']);
        }
        $this->actingAs($viewer)->get(route('following'))->assertInertia(fn ($page) => $page
            ->where('followingCount', 21)->has('followingUsers.data', 20)->has('posts', 15));
        $this->get(route('following', ['users_page' => 2]))->assertInertia(fn ($page) => $page
            ->has('followingUsers.data', 1)->has('posts', 15));
        $first = $this->getJson(route('following'))->assertJsonCount(15, 'posts')->json();
        $next = $this->getJson(route('following', ['cursor' => $first['nextCursor']]))->assertJsonCount(6, 'posts')->json();
        $this->assertEmpty(array_intersect(array_column($first['posts'], 'id'), array_column($next['posts'], 'id')));
    }

    public function test_following_includes_viewer_like_and_save_state_in_page_and_cursor_responses(): void
    {
        [$viewer, $artist, $other] = User::factory()->count(3)->create();
        Follow::create(['follower_id' => $viewer->id, 'following_id' => $artist->id]);
        $work = Post::create(['user_id' => $artist->id, 'title' => 'Reacted work', 'type' => 'image', 'rating' => 'sfw']);
        $unrelated = Post::create(['user_id' => $artist->id, 'title' => 'Other work', 'type' => 'image', 'rating' => 'sfw']);
        $work->likes()->create(['user_id' => $viewer->id]);
        $work->bookmarkedBy()->attach($viewer->id);
        $unrelated->likes()->create(['user_id' => $other->id]);
        $unrelated->bookmarkedBy()->attach($other->id);

        $this->actingAs($viewer)->get(route('following'))->assertInertia(fn ($page) => $page
            ->where('posts.0.liked', false)->where('posts.0.saved', false)
            ->where('posts.1.liked', true)->where('posts.1.saved', true));
        $this->getJson(route('following'))->assertOk()
            ->assertJsonPath('posts.0.liked', false)->assertJsonPath('posts.0.saved', false)
            ->assertJsonPath('posts.1.liked', true)->assertJsonPath('posts.1.saved', true);
    }
}
