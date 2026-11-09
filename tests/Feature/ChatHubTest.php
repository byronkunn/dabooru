<?php

namespace Tests\Feature;

use App\Models\Conversation;
use App\Models\Message;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Tests\Feature\Concerns\MakesImages;
use Tests\TestCase;

class ChatHubTest extends TestCase
{
    use MakesImages, RefreshDatabase;

    public function test_strangers_can_send_one_text_request_and_only_the_recipient_can_accept(): void
    {
        [$sender, $recipient, $outsider] = User::factory()->count(3)->create();
        $this->actingAs($sender)->get(route('messages.index', ['to' => $recipient->username]))->assertRedirect();
        $chat = Conversation::sole();
        $url = route('messages.show', $chat);
        $decision = route('messages.request', $chat);
        $this->actingAs($recipient)->get($url)->assertInertia(fn ($page) => $page->has('conversation.messages', 0));

        $this->actingAs($sender)->post(route('messages.store'), [
            'conversation_id' => $chat->id, 'file' => $this->pngUpload('request.png'),
        ])->assertSessionHasErrors('body');
        $this->post(route('messages.store'), ['conversation_id' => $chat->id, 'body' => 'Can we talk?'])->assertRedirect($url);
        $this->post(route('messages.store'), ['conversation_id' => $chat->id, 'body' => 'Another request'])->assertForbidden();
        $this->post($decision, ['decision' => 'accepted'])->assertForbidden();
        $this->actingAs($outsider)->post($decision, ['decision' => 'accepted'])->assertForbidden();

        $this->actingAs($recipient)->get(route('messages.index'))->assertInertia(fn ($page) => $page->has('conversations', 0));
        $this->get(route('messages.index', ['tab' => 'requests']))->assertInertia(fn ($page) => $page
            ->where('conversations.0.preview', 'Can we talk?'));
        $this->post($decision, ['decision' => 'accepted'])->assertRedirect();
        $this->post(route('messages.store'), ['conversation_id' => $chat->id, 'body' => 'Yes'])->assertRedirect($url);
        $this->assertSame('accepted', $chat->fresh()->request_status);
    }

    public function test_privacy_denies_new_requests_from_accounts_the_recipient_does_not_follow(): void
    {
        [$sender, $recipient] = User::factory()->count(2)->create();
        $this->actingAs($recipient)->put(route('messages.privacy'), [
            'chat_dm_permission' => 'following', 'chat_read_receipts' => true,
        ])->assertRedirect();

        $this->actingAs($sender)->post(route('messages.store'), ['to' => $recipient->username, 'body' => 'Hello'])->assertForbidden();

        $recipient->following()->attach($sender->id);
        $this->post(route('messages.store'), ['to' => $recipient->username, 'body' => 'Hello again'])->assertRedirect();
        $this->assertSame('pending', Conversation::where('kind', 'direct')->sole()->request_status);
    }

    public function test_followed_senders_still_require_a_text_request_and_explicit_acceptance(): void
    {
        Storage::fake('local');
        [$sender, $recipient] = User::factory()->count(2)->create();
        $recipient->following()->attach($sender->id);
        $this->actingAs($sender)->get(route('messages.index', ['to' => $recipient->username]))->assertRedirect();
        $chat = Conversation::sole();
        $this->get(route('messages.show', $chat))->assertInertia(fn ($page) => $page
            ->where('conversation.request.textOnly', true));

        foreach ([
            ['file' => $this->pngUpload('request.png')],
            ['shared_post_id' => 999],
            ['sticker_pack_id' => 999, 'sticker_post_id' => 999],
            ['forward_id' => 999],
            ['reply_to_id' => 999],
            ['interactive' => ['type' => 'poll', 'title' => 'Question', 'options' => ['Yes', 'No']]],
            ['send_at' => now()->addHour()->toIso8601String()],
            ['spoiler' => true],
            ['silent' => true],
        ] as $extra) {
            $this->post(route('messages.store'), [
                'conversation_id' => $chat->id, 'body' => 'Hello',
            ] + $extra)->assertSessionHasErrors('body');
            $this->assertSame('draft', $chat->fresh()->request_status);
            $this->assertDatabaseCount('messages', 0);
        }

        $this->post(route('messages.store'), ['conversation_id' => $chat->id, 'body' => 'Hello'])->assertRedirect();
        foreach ([$sender, $recipient] as $user) {
            $this->actingAs($user)->post(route('messages.store'), [
                'conversation_id' => $chat->id, 'body' => 'Not accepted yet',
            ])->assertForbidden();
        }
        $this->assertDatabaseCount('messages', 1);
        $this->actingAs($recipient)->post(route('messages.request', $chat), ['decision' => 'accepted'])->assertRedirect();
        $this->actingAs($sender)->post(route('messages.store'), [
            'conversation_id' => $chat->id, 'file' => $this->pngUpload('accepted.png'),
        ])->assertRedirect();
        $this->assertNotNull(Message::latest('id')->firstOrFail()->media_id);
    }

    public function test_search_respects_per_user_deletion(): void
    {
        [$sender, $recipient] = User::factory()->count(2)->create();
        $chat = Conversation::between($sender->id, $recipient->id);
        $message = $chat->messages()->create(['user_id' => $sender->id, 'body' => 'Chapter announcement']);
        $search = route('messages.index', ['q' => 'announcement']);

        $this->actingAs($recipient)->get($search)->assertInertia(fn ($page) => $page->has('conversations', 1));
        $this->post(route('messages.hide', $message))->assertRedirect();
        $this->get($search)->assertInertia(fn ($page) => $page->has('conversations', 0));
        $this->actingAs($sender)->get($search)->assertInertia(fn ($page) => $page->has('conversations', 1));
    }

    public function test_message_tools_and_scheduling_work_without_exposing_other_threads(): void
    {
        $this->freezeSecond();
        [$sender, $recipient, $outsider] = User::factory()->count(3)->create();
        $chat = Conversation::between($sender->id, $recipient->id);
        $private = Conversation::between($recipient->id, $outsider->id);
        $original = $chat->messages()->create(['user_id' => $recipient->id, 'body' => 'Original']);
        $secret = $private->messages()->create(['user_id' => $outsider->id, 'body' => 'Secret']);

        $this->actingAs($sender)->post(route('messages.store'), ['conversation_id' => $chat->id, 'forward_id' => $secret->id])->assertForbidden();
        $this->post(route('messages.store'), ['conversation_id' => $chat->id, 'reply_to_id' => $original->id, 'body' => 'Reply'])->assertRedirect();
        $reply = Message::latest('id')->firstOrFail();
        $this->patch(route('messages.edit', $reply), ['body' => 'Edited reply'])->assertRedirect();
        $this->put(route('messages.react', $original), ['emoji' => '🎨'])->assertRedirect();
        $this->put(route('messages.pin', $original), ['pinned' => true])->assertRedirect();
        $this->post(route('messages.typing', $chat))->assertNoContent();
        $this->post(route('messages.store'), [
            'conversation_id' => $chat->id, 'body' => 'Later', 'send_at' => now()->addMinutes(2)->toIso8601String(), 'silent' => true,
        ])->assertRedirect();
        $this->assertDatabaseHas('scheduled_messages', ['body' => 'Later', 'status' => 'pending']);
        $this->actingAs($recipient)->get(route('messages.show', $chat))->assertInertia(fn ($page) => $page
            ->where('conversation.pinned.label', 'Original')
            ->where('conversation.messages.0.reactions.0.emoji', '🎨')
            ->where('conversation.messages.1.reply.label', 'Original')
            ->where('conversation.messages.1.edited', true)
            ->where('conversation.typing.0', $sender->name));
        $this->postJson(route('messages.read', $chat), ['message_id' => $reply->id])->assertOk();
        $this->postJson(route('messages.read', $private), ['message_id' => $secret->id])->assertOk();
        $this->travel(3)->minutes();
        $this->artisan('messages:send-scheduled')->assertSuccessful();
        $this->artisan('messages:send-scheduled')->assertSuccessful();
        $this->assertSame(1, Message::where('body', 'Later')->count());
        $this->get(route('messages.index'))->assertInertia(fn ($page) => $page
            ->where('auth.unread_messages', 0)->where('conversations.0.unread', 1));
    }

    public function test_saved_messages_are_private_and_reused_and_polls_have_one_vote_per_person(): void
    {
        [$owner, $outsider] = User::factory()->count(2)->create();
        $this->actingAs($owner)->post(route('messages.rooms.store'), ['kind' => 'saved'])->assertRedirect();
        $saved = Conversation::sole();
        $this->post(route('messages.rooms.store'), ['kind' => 'saved'])->assertRedirect(route('messages.show', $saved));
        $this->actingAs($outsider)->get(route('messages.show', $saved))->assertForbidden();
        $chat = Conversation::between($owner->id, $outsider->id);
        $this->actingAs($owner)->post(route('messages.store'), ['conversation_id' => $chat->id, 'interactive' => [
            'type' => 'poll', 'title' => 'Which cover?', 'options' => ['Blue', 'Red'],
        ]])->assertRedirect();
        $poll = Message::sole();
        $this->actingAs($outsider)->put(route('messages.choice', $poll), ['index' => 0, 'selected' => true])->assertRedirect();
        $this->put(route('messages.choice', $poll), ['index' => 1, 'selected' => true])->assertRedirect();
        $this->assertSame([1], DB::table('message_choices')->pluck('option_index')->all());
    }
}
