<?php

namespace Tests\Feature;

use App\Models\Comment;
use App\Models\Post;
use App\Models\User;
use Database\Seeders\MediaCommentsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class MediaCommentsSeederTest extends TestCase
{
    use RefreshDatabase;

    public function test_sample_discussions_cover_all_media_and_are_repeatable_without_losing_existing_comments(): void
    {
        $users = User::factory()->count(3)->create();
        $posts = collect(Post::TYPES)->map(fn ($type, $i) => Post::create([
            'type' => $type, 'rating' => 'sfw', 'user_id' => $users[$i % 3]->id,
        ]));
        $draft = Post::create(['type' => 'video', 'rating' => 'sfw', 'user_id' => $users[0]->id, 'is_draft' => true]);
        $deleted = Post::create(['type' => 'image', 'rating' => 'sfw', 'user_id' => $users[0]->id]);
        $deleted->delete();
        $existing = Comment::create(['post_id' => $posts[0]->id, 'user_id' => $users[0]->id, 'body' => 'Keep this existing discussion.']);
        $this->seed(MediaCommentsSeeder::class);
        $this->seed(MediaCommentsSeeder::class);
        foreach ($posts as $post) {
            $this->assertSame(5, $post->comments()->whereNull('parent_id')->where('body', 'like', '[Sample comment]%')->count());
            $this->assertSame(5, $post->comments()->whereNotNull('parent_id')->count());
            $this->assertSame($post->id === $posts[0]->id ? 11 : 10, $post->fresh()->comments_count);
            foreach ($post->comments()->whereNotNull('parent_id')->get() as $reply) {
                $this->assertSame($post->id, Comment::findOrFail($reply->parent_id)->post_id);
            }
        }
        $this->assertSame('Keep this existing discussion.', $existing->fresh()->body);
        $this->assertSame(0, $draft->comments()->count());
        $this->assertSame(0, $deleted->comments()->count());
        $this->assertDatabaseCount('notifications_app', 0);
        $sample = $posts[1]->comments()->whereNull('parent_id')->firstOrFail();
        $sample->delete();
        $this->seed(MediaCommentsSeeder::class);
        $this->assertTrue($sample->fresh()->trashed());
        $this->assertDatabaseCount('comments', 51);
    }

    public function test_media_page_includes_replies_only_on_their_parent_and_excludes_deleted_replies(): void
    {
        $user = User::factory()->create();
        $post = Post::create(['type' => 'image', 'rating' => 'sfw', 'user_id' => $user->id]);
        $this->seed(MediaCommentsSeeder::class);
        $reply = $post->comments()->whereNotNull('parent_id')->firstOrFail();
        $reply->delete();
        $this->actingAs($user)->get(route('posts.show', $post))->assertInertia(fn ($page) => $page
            ->component('Posts/Show')->has('comments', 5)
            ->where('comments', fn ($comments) => collect($comments)->sum(fn ($comment) => count($comment['replies'])) === 4));
    }

    public function test_sample_discussions_refuse_to_run_in_production(): void
    {
        $this->app->instance('env', 'production');
        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessage('only available in local development');
        (new MediaCommentsSeeder)->run();
    }
}
