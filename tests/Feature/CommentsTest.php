<?php

namespace Tests\Feature;

use App\Models\AppNotification;
use App\Models\Block;
use App\Models\Comment;
use App\Models\Post;
use App\Models\Setting;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class CommentsTest extends TestCase
{
    use RefreshDatabase;

    private function work(User $user): Post
    {
        return Post::create(['user_id' => $user->id, 'type' => 'image', 'rating' => 'sfw']);
    }

    public function test_comments_and_replies_persist_and_notify_without_duplicates(): void
    {
        $artist = User::factory()->create();
        $fan = User::factory()->create();
        $other = User::factory()->create();
        $post = $this->work($artist);
        $this->actingAs($fan)->post(route('comments.store', $post), ['body' => '  Beautiful work  '])->assertSessionHasNoErrors();
        $parent = Comment::firstOrFail();
        $this->assertSame('Beautiful work', $parent->body);
        $this->actingAs($other)->post(route('comments.store', $post), ['body' => 'Agreed', 'parent_id' => $parent->id])->assertSessionHasNoErrors();
        $this->assertSame(2, $post->fresh()->comments_count);
        $this->assertDatabaseHas('comments', ['body' => 'Agreed', 'parent_id' => $parent->id, 'post_id' => $post->id]);
        $this->assertSame(3, AppNotification::count());
        $this->assertTrue(AppNotification::where('user_id', $fan->id)->firstOrFail()->data['reply']);
        $ownParent = Comment::create(['post_id' => $post->id, 'user_id' => $artist->id, 'body' => 'Thanks']);
        $this->post(route('comments.store', $post), ['body' => 'Welcome', 'parent_id' => $ownParent->id])->assertSessionHasNoErrors();
        $this->assertSame(4, AppNotification::count());
        Setting::updateOrCreate(['user_id' => $fan->id, 'key' => 'notification_comments'], ['value' => false]);
        $this->actingAs($artist)->post(route('comments.store', $post), ['body' => 'Thanks', 'parent_id' => $parent->id])->assertSessionHasNoErrors();
        $this->assertSame(4, AppNotification::count());
    }

    public function test_invalid_bodies_and_unavailable_or_cross_post_parents_are_rejected_atomically(): void
    {
        $user = User::factory()->create();
        $post = $this->work($user);
        $parent = Comment::create(['post_id' => $this->work($user)->id, 'user_id' => $user->id, 'body' => 'Elsewhere']);
        $this->actingAs($user);
        foreach (['   ', str_repeat('x', 2001)] as $body) {
            $this->post(route('comments.store', $post), ['body' => $body])->assertSessionHasErrors('body');
        }
        foreach ([$parent->id, 0, 9999] as $id) {
            $this->post(route('comments.store', $post), ['body' => 'Reply', 'parent_id' => $id])->assertSessionHasErrors('parent_id');
        }
        $local = Comment::create(['post_id' => $post->id, 'user_id' => $user->id, 'body' => 'Local']);
        $child = Comment::create(['post_id' => $post->id, 'user_id' => $user->id, 'parent_id' => $local->id, 'body' => 'Child']);
        $this->post(route('comments.store', $post), ['body' => 'Reply', 'parent_id' => $child->id])->assertSessionHasErrors('parent_id');
        $local->delete();
        $this->post(route('comments.store', $post), ['body' => 'Reply', 'parent_id' => $local->id])->assertSessionHasErrors('parent_id');
        $this->assertSame(0, $post->fresh()->comments_count);
        $this->assertDatabaseCount('notifications_app', 0);
    }

    public function test_only_comment_owner_can_edit_and_deleted_parent_keeps_replies_without_exposing_body(): void
    {
        $artist = User::factory()->create();
        $fan = User::factory()->create();
        $post = $this->work($artist);
        $parent = Comment::create(['post_id' => $post->id, 'user_id' => $fan->id, 'body' => 'Original']);
        $reply = Comment::create(['post_id' => $post->id, 'user_id' => $artist->id, 'parent_id' => $parent->id, 'body' => 'Reply']);
        $this->actingAs($artist)->patch(route('comments.update', $parent), ['body' => 'Hijacked'])->assertForbidden();
        $this->actingAs($fan)->patch(route('comments.update', $parent), ['body' => '  Edited  '])->assertSessionHasNoErrors();
        $this->assertSame('Edited', $parent->fresh()->body);
        $this->patch(route('comments.update', $parent), ['body' => ' '])->assertSessionHasErrors('body');
        $this->delete(route('comments.destroy', $parent))->assertRedirect();
        $this->assertSame(1, $post->fresh()->comments_count);
        $this->get(route('posts.show', $post))->assertInertia(fn ($page) => $page
            ->where('comments.0.deleted', true)->where('comments.0.body', null)->where('comments.0.user', null)
            ->where('comments.0.canEdit', false)->where('comments.0.canReply', false)
            ->where('comments.0.replies.0.id', $reply->id));
        $this->patch(route('comments.update', $parent), ['body' => 'Resurrected'])->assertNotFound();
        $this->actingAs($artist)->delete(route('comments.destroy', $reply))->assertRedirect();
        $this->get(route('posts.show', $post))->assertInertia(fn ($page) => $page->has('comments', 0));
        $this->assertSame(0, $post->fresh()->comments_count);
    }

    public function test_guest_blocked_and_draft_comment_permissions_are_enforced(): void
    {
        $artist = User::factory()->create();
        $fan = User::factory()->create();
        $third = User::factory()->create();
        $post = $this->work($artist);
        $this->post(route('comments.store', $post), ['body' => 'Guest'])->assertRedirect(route('login'));
        Block::create(['blocker_id' => $artist->id, 'blocked_id' => $fan->id]);
        $this->actingAs($fan)->post(route('comments.store', $post), ['body' => 'Blocked'])->assertForbidden();
        $this->get(route('posts.show', $post))->assertNotFound();
        $otherPost = $this->work($third);
        $parent = Comment::create(['post_id' => $otherPost->id, 'user_id' => $artist->id, 'body' => 'Parent']);
        $this->post(route('comments.store', $otherPost), ['body' => 'Blocked reply', 'parent_id' => $parent->id])->assertForbidden();
        $draft = Post::create(['user_id' => $fan->id, 'type' => 'image', 'rating' => 'sfw', 'is_draft' => true]);
        $this->post(route('comments.store', $draft), ['body' => 'Draft'])->assertNotFound();
        $this->assertDatabaseCount('notifications_app', 0);
    }

    public function test_all_threads_can_be_reached_through_stable_pagination(): void
    {
        $artist = User::factory()->create();
        $post = $this->work($artist);
        for ($i = 0; $i < 25; $i++) {
            Comment::create(['post_id' => $post->id, 'user_id' => $artist->id, 'body' => 'Comment '.$i]);
        }
        $this->get(route('posts.show', $post))->assertInertia(fn ($page) => $page
            ->has('comments', 20)->where('comments.0.body', 'Comment 24')
            ->where('commentPagination.previousPage', null)->where('canComment', false));
        $this->get(route('posts.show', ['post' => $post, 'comment_page' => 2]))->assertInertia(fn ($page) => $page
            ->has('comments', 5)->where('comments.0.body', 'Comment 4')->where('commentPagination.nextPage', null));
        $this->actingAs($artist)->from(route('posts.show', ['post' => $post, 'comment_page' => 2]))
            ->post(route('comments.store', $post), ['body' => 'Newest comment'])
            ->assertRedirect(route('posts.show', $post).'#comments');
    }

    public function test_guests_cannot_edit_or_delete_comments(): void
    {
        $artist = User::factory()->create();
        $fan = User::factory()->create();
        $post = $this->work($artist);
        Comment::create(['post_id' => $post->id, 'user_id' => $fan->id, 'body' => 'A comment']);
        $this->get(route('posts.show', $post))->assertInertia(fn ($page) => $page
            ->where('comments.0.canEdit', false)
            ->where('comments.0.canDelete', false)->where('comments.0.canReply', false));
    }
}
