<?php

namespace Tests\Feature;

use App\Models\Conversation;
use App\Models\Message;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class InlineButtonsTest extends TestCase
{
    use RefreshDatabase;

    private function thread(): array
    {
        $a = User::factory()->create(['username' => 'asker']);
        $b = User::factory()->create(['username' => 'answerer']);
        $c = Conversation::between($a->id, $b->id);
        // an inline keyboard only makes sense once the chat is live
        $c->forceFill(['request_status' => 'accepted'])->save();

        return [$a, $b, $c];
    }

    public function test_an_inline_button_message_can_be_sent(): void
    {
        [$a, , $c] = $this->thread();

        $this->actingAs($a)->post(route('messages.store'), [
            'conversation_id' => $c->id,
            'interactive' => [
                'type' => 'buttons',
                'title' => 'Trade for the original?',
                'options' => ['Yes', 'No'],
            ],
        ])->assertRedirect();

        $message = Message::sole();
        $this->assertSame('buttons', $message->interactive['type']);
        $this->assertSame(['Yes', 'No'], $message->interactive['options']);
    }

    public function test_a_single_button_is_allowed(): void
    {
        [$a, , $c] = $this->thread();

        $this->actingAs($a)->post(route('messages.store'), [
            'conversation_id' => $c->id,
            'interactive' => ['type' => 'buttons', 'title' => 'Got it', 'options' => ['Acknowledge']],
        ])->assertRedirect();

        $this->assertSame(['Acknowledge'], Message::sole()->interactive['options']);
    }

    public function test_a_poll_still_needs_two_options(): void
    {
        [$a, , $c] = $this->thread();

        $this->actingAs($a)->post(route('messages.store'), [
            'conversation_id' => $c->id,
            'interactive' => ['type' => 'poll', 'title' => 'Only one?', 'options' => ['Solo']],
        ])->assertSessionHasErrors('interactive.options');
    }

    public function test_the_recipient_answers_and_the_message_settles(): void
    {
        [$a, $b, $c] = $this->thread();

        $this->actingAs($a)->post(route('messages.store'), [
            'conversation_id' => $c->id,
            'interactive' => ['type' => 'buttons', 'title' => 'Trade?', 'options' => ['Yes', 'No']],
        ]);
        $message = Message::sole();

        $this->actingAs($b)->put(route('messages.choice', $message), ['index' => 0, 'selected' => true])
            ->assertRedirect();

        $message->refresh();
        $this->assertSame($b->id, $message->interactive['answered_by']);
        $this->assertSame('Yes', $message->interactive['options'][$message->interactive['answer_index']]);
    }

    public function test_a_second_answer_is_rejected(): void
    {
        [$a, $b, $c] = $this->thread();
        $this->actingAs($a)->post(route('messages.store'), [
            'conversation_id' => $c->id,
            'interactive' => ['type' => 'buttons', 'title' => 'Trade?', 'options' => ['Yes', 'No']],
        ]);
        $message = Message::sole();

        $this->actingAs($b)->put(route('messages.choice', $message), ['index' => 0, 'selected' => true]);
        $this->actingAs($b)->put(route('messages.choice', $message), ['index' => 1, 'selected' => true])
            ->assertStatus(409);

        $message->refresh();
        $this->assertSame(0, $message->interactive['answer_index'], 'the first answer stands');
    }

    public function test_an_outsider_cannot_answer(): void
    {
        [$a, , $c] = $this->thread();
        $this->actingAs($a)->post(route('messages.store'), [
            'conversation_id' => $c->id,
            'interactive' => ['type' => 'buttons', 'title' => 'Trade?', 'options' => ['Yes']],
        ]);
        $message = Message::sole();

        $this->actingAs(User::factory()->create())
            ->put(route('messages.choice', $message), ['index' => 0, 'selected' => true])
            ->assertForbidden();
    }
}
