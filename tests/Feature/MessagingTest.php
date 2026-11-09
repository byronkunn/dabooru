<?php

namespace Tests\Feature;

use App\Models\Conversation;
use App\Models\Message;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class MessagingTest extends TestCase
{
    use RefreshDatabase;

    public function test_opening_a_profile_chat_twice_reuses_the_same_conversation(): void
    {
        $sender = User::factory()->create();
        $recipient = User::factory()->create();
        $url = route('messages.index', ['to' => $recipient->username]);

        $this->actingAs($sender)->get($url)->assertRedirect();
        $thread = Conversation::sole();
        $this->get($url)->assertRedirect(route('messages.show', $thread));
        $this->assertEqualsCanonicalizing(
            [$sender->id, $recipient->id],
            $thread->participants()->pluck('users.id')->all(),
        );
        $this->assertDatabaseCount('conversations', 1);
    }

    public function test_only_acknowledging_a_visible_message_clears_unread(): void
    {
        $sender = User::factory()->create();
        $recipient = User::factory()->create();
        $thread = Conversation::between($sender->id, $recipient->id);

        $this->actingAs($sender)->post(route('messages.store'), [
            'conversation_id' => $thread->id,
            'body' => 'Synthetic QA message',
        ])->assertRedirect(route('messages.show', $thread));

        $this->actingAs($recipient)->get(route('messages.index'))->assertInertia(fn ($page) => $page
            ->component('Messages/Index')
            ->where('auth.unread_messages', 1)
            ->where('conversations.0.preview', 'Synthetic QA message'));

        $this->get(route('messages.show', $thread))->assertInertia(fn ($page) => $page
            ->component('Messages/Show')
            ->where('conversation.messages.0.body', 'Synthetic QA message')
            ->where('conversation.messages.0.mine', false)
            ->where('auth.unread_messages', 1));
        $this->postJson(route('messages.read', $thread), ['message_id' => Message::sole()->id])
            ->assertOk()->assertJsonPath('unread', 0);
        $this->get(route('messages.index'))->assertInertia(fn ($page) => $page->where('auth.unread_messages', 0));
    }

    public function test_only_participants_can_report_a_message_and_only_its_sender_can_remove_it(): void
    {
        $sender = User::factory()->create();
        $recipient = User::factory()->create();
        $outsider = User::factory()->create();
        $thread = Conversation::between($sender->id, $recipient->id);
        $message = $thread->messages()->create(['user_id' => $sender->id, 'body' => 'Synthetic QA message']);
        $report = ['reportable_type' => 'message', 'reportable_id' => $message->id, 'reason' => 'spam'];

        $this->actingAs($outsider)->post(route('reports.store'), $report)->assertNotFound();
        $this->assertDatabaseCount('reports', 0);
        $this->actingAs($recipient)->post(route('reports.store'), $report)->assertRedirect();
        $this->assertDatabaseHas('reports', [
            'reportable_type' => Message::class,
            'reportable_id' => $message->id,
            'reporter_id' => $recipient->id,
            'status' => 'open',
        ]);

        $this->delete(route('messages.destroy', $message))->assertForbidden();
        $this->actingAs($sender)->delete(route('messages.destroy', $message))->assertRedirect();
        $this->assertSame('[deleted]', $message->fresh()->body);
    }

    public function test_blocks_stop_sends_in_both_directions_and_keep_existing_history_readable(): void
    {
        $blocker = User::factory()->create();
        $blocked = User::factory()->create();
        $thread = Conversation::between($blocker->id, $blocked->id);
        $thread->messages()->create(['user_id' => $blocked->id, 'body' => 'Before the block']);

        $this->actingAs($blocker)->post(route('users.block', $blocked))->assertRedirect();
        $payload = ['conversation_id' => $thread->id, 'body' => 'After the block'];
        $this->post(route('messages.store'), $payload)->assertForbidden();

        $this->actingAs($blocked)->post(route('messages.store'), $payload)->assertForbidden();
        $this->post(route('messages.store'), ['to' => $blocker->username, 'body' => 'Try by username'])->assertForbidden();
        $this->get(route('messages.index', ['to' => $blocker->username]))->assertForbidden();
        $this->get(route('messages.show', $thread))->assertInertia(fn ($page) => $page
            ->where('conversation.canSend', false)->has('conversation.messages', 1));
        $this->assertDatabaseCount('conversations', 1);
        $this->assertDatabaseCount('messages', 1);

        $this->actingAs($blocker)->delete(route('users.unblock', $blocked))->assertRedirect();
        $this->actingAs($blocked)->post(route('messages.store'), $payload)->assertRedirect(route('messages.show', $thread));
    }

    public function test_blocked_users_cannot_create_a_first_thread(): void
    {
        $blocker = User::factory()->create();
        $blocked = User::factory()->create();

        $this->actingAs($blocker)->post(route('users.block', $blocked))->assertRedirect();
        $this->actingAs($blocked)->post(route('messages.store'), [
            'to' => $blocker->username, 'body' => 'Try a new thread',
        ])->assertForbidden();
        $this->get(route('messages.index', ['to' => $blocker->username]))->assertForbidden();
        $this->assertDatabaseCount('conversations', 0);
    }

    public function test_a_send_accepts_a_username_but_rejects_missing_conflicting_or_self_destinations(): void
    {
        $sender = User::factory()->create();
        $recipient = User::factory()->create();
        $url = route('messages.store');

        $this->actingAs($sender)->postJson($url, ['body' => 'No destination'])
            ->assertUnprocessable()->assertJsonValidationErrors('conversation_id');
        $this->postJson($url, ['body' => 'Self', 'to' => $sender->username])
            ->assertUnprocessable()->assertJsonValidationErrors('to');
        $this->post($url, ['body' => 'Direct send', 'to' => $recipient->username])->assertRedirect();
        $thread = Conversation::sole();

        $this->postJson($url, ['body' => 'Two destinations', 'to' => $recipient->username, 'conversation_id' => $thread->id])
            ->assertUnprocessable()->assertJsonValidationErrors('conversation_id');
        $this->assertSame('Direct send', Message::sole()->body);
        $this->assertEqualsCanonicalizing([$sender->id, $recipient->id], $thread->participants()->pluck('users.id')->all());
    }

    public function test_inbox_and_switcher_use_the_latest_message_even_when_timestamps_tie(): void
    {
        $this->freezeSecond();
        $alice = User::factory()->create();
        $bob = User::factory()->create();
        $thread = Conversation::between($alice->id, $bob->id);
        $thread->messages()->create(['user_id' => $alice->id, 'body' => 'First']);
        $thread->messages()->create(['user_id' => $bob->id, 'body' => 'Latest']);

        $this->actingAs($alice)->get(route('messages.index'))->assertInertia(fn ($page) => $page
            ->where('conversations.0.preview', 'Latest'));
        $this->get(route('messages.show', $thread))->assertInertia(fn ($page) => $page
            ->where('conversations.0.preview', 'Latest')
            ->where('conversation.messages.0.body', 'First'));
    }

    public function test_a_same_second_reply_counts_as_unread_after_opening_a_thread(): void
    {
        $this->freezeSecond();
        $alice = User::factory()->create();
        $bob = User::factory()->create();
        $thread = Conversation::between($alice->id, $bob->id);
        $first = $thread->messages()->create(['user_id' => $bob->id, 'body' => 'First']);

        $this->actingAs($alice)->postJson(route('messages.read', $thread), ['message_id' => $first->id])
            ->assertOk()->assertJsonPath('unread', 0);
        $this->actingAs($bob)->post(route('messages.store'), [
            'conversation_id' => $thread->id, 'body' => 'Same second',
        ])->assertRedirect();
        $this->actingAs($alice)->get(route('messages.index'))->assertInertia(fn ($page) => $page
            ->where('auth.unread_messages', 1));
        $this->postJson(route('messages.read', $thread), ['message_id' => Message::latest('id')->firstOrFail()->id])
            ->assertOk()->assertJsonPath('unread', 0);
    }
}
