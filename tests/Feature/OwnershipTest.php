<?php

namespace Tests\Feature;

use App\Models\Comment;
use App\Models\Message;
use App\Models\Post;
use App\Models\User;
use App\Models\Conversation;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class OwnershipTest extends TestCase
{
    use RefreshDatabase;

    private function postOf(User $user): Post
    {
        return Post::create(['user_id' => $user->id, 'type' => 'image', 'rating' => 'sfw', 'title' => 'Mine']);
    }

    /* ------------------------------------------------------------ posts */

    public function test_owner_can_edit_their_post(): void
    {
        $owner = User::factory()->create();
        $post = $this->postOf($owner);

        $this->actingAs($owner)
            ->patch(route('posts.update', $post), ['title' => 'New title', 'caption' => 'New caption', 'rating' => 'suggestive'])
            ->assertRedirect(route('posts.show', $post));

        $post->refresh();
        $this->assertSame('New title', $post->title);
        $this->assertSame('suggestive', $post->rating);
    }

    public function test_someone_else_cannot_edit_or_delete_a_post(): void
    {
        $owner = User::factory()->create();
        $intruder = User::factory()->create();
        $post = $this->postOf($owner);

        $this->actingAs($intruder)->get(route('posts.edit', $post))->assertForbidden();
        $this->actingAs($intruder)->patch(route('posts.update', $post), ['title' => 'hijacked', 'rating' => 'sfw'])->assertForbidden();
        $this->actingAs($intruder)->delete(route('posts.destroy', $post))->assertForbidden();

        $this->assertSame('Mine', $post->fresh()->title);
        $this->assertNull($post->fresh()->deleted_at);
    }

    public function test_owner_can_delete_their_post_and_it_leaves_the_gallery(): void
    {
        $owner = User::factory()->create();
        $post = $this->postOf($owner);

        $this->actingAs($owner)->delete(route('posts.destroy', $post))->assertRedirect();

        $this->assertSoftDeleted('posts', ['id' => $post->id]);
        $this->get('/')->assertInertia(fn ($page) => $page->has('posts', 0));
    }

    public function test_edit_page_shows_the_post_media(): void
    {
        $owner = User::factory()->create();
        $post = $this->postOf($owner);

        $this->actingAs($owner)->get(route('posts.edit', $post))->assertOk()
            ->assertInertia(fn ($page) => $page->component('Posts/Edit')->where('post.title', 'Mine'));
    }

    /* --------------------------------------------------------- comments */

    public function test_owner_can_delete_their_comment_and_the_count_follows(): void
    {
        $artist = User::factory()->create();
        $fan = User::factory()->create();
        $post = $this->postOf($artist);
        $post->forceFill(['comments_count' => 1])->save();
        $comment = Comment::create(['post_id' => $post->id, 'user_id' => $fan->id, 'body' => 'nice']);

        $this->actingAs($fan)->delete(route('comments.destroy', $comment))->assertRedirect();

        $this->assertSoftDeleted('comments', ['id' => $comment->id]);
        $this->assertSame(0, $post->fresh()->comments_count);
    }

    public function test_only_the_comment_author_can_delete_it(): void
    {
        $artist = User::factory()->create();
        $fan = User::factory()->create();
        $other = User::factory()->create();
        $post = $this->postOf($artist);
        $comment = Comment::create(['post_id' => $post->id, 'user_id' => $fan->id, 'body' => 'nice']);

        // not even the post owner can delete someone else's comment from here
        $this->actingAs($other)->delete(route('comments.destroy', $comment))->assertForbidden();
        $this->actingAs($artist)->delete(route('comments.destroy', $comment))->assertForbidden();

        $this->assertNull($comment->fresh()->deleted_at);
    }

    /* --------------------------------------------------------- messages */

    public function test_author_can_delete_their_message_body(): void
    {
        $a = User::factory()->create();
        $b = User::factory()->create();
        $conversation = Conversation::between($a->id, $b->id);
        $message = Message::create(['conversation_id' => $conversation->id, 'user_id' => $a->id, 'body' => 'oops']);

        $this->actingAs($a)->delete(route('messages.destroy', $message))->assertRedirect();
        $this->assertSame('[deleted]', $message->fresh()->body);

        // the recipient cannot delete the sender's message
        $message2 = Message::create(['conversation_id' => $conversation->id, 'user_id' => $a->id, 'body' => 'mine']);
        $this->actingAs($b)->delete(route('messages.destroy', $message2))->assertForbidden();
    }
}
