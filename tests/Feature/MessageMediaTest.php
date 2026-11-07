<?php

namespace Tests\Feature;

use App\Models\Conversation;
use App\Models\Media;
use App\Models\Message;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Storage;
use Tests\Feature\Concerns\MakesImages;
use Tests\TestCase;

class MessageMediaTest extends TestCase
{
    use MakesImages, RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        Storage::fake('public', ['url' => '/storage']);
    }

    private function pair(): array
    {
        $a = User::factory()->create(['username' => 'talker-a']);
        $b = User::factory()->create(['username' => 'talker-b']);
        $conversation = Conversation::between($a->id, $b->id);

        return [$a, $b, $conversation];
    }

    public function test_a_message_can_carry_an_image(): void
    {
        [$a, , $conversation] = $this->pair();

        $this->actingAs($a)->post(route('messages.store'), [
            'conversation_id' => $conversation->id,
            'file' => $this->pngUpload('shot.png', 300, 200),
        ])->assertRedirect();

        $message = Message::where('conversation_id', $conversation->id)->latest('id')->firstOrFail();
        $this->assertNotNull($message->media_id);
        $this->assertSame('image', $message->media->kind);
        $this->assertSame(300, $message->media->width);
    }

    public function test_a_message_can_carry_an_image_and_text(): void
    {
        [$a, , $conversation] = $this->pair();

        $this->actingAs($a)->post(route('messages.store'), [
            'conversation_id' => $conversation->id,
            'body' => 'See attached.',
            'file' => $this->pngUpload('shot.png'),
        ])->assertRedirect();

        $message = Message::latest('id')->firstOrFail();
        $this->assertSame('See attached.', $message->body);
        $this->assertNotNull($message->media_id);
    }

    public function test_a_message_needs_text_or_an_attachment(): void
    {
        [$a, , $conversation] = $this->pair();

        $this->actingAs($a)->post(route('messages.store'), [
            'conversation_id' => $conversation->id,
        ])->assertSessionHasErrors(['body']);

        $this->assertSame(0, Message::count());
    }

    public function test_unsupported_attachments_are_rejected(): void
    {
        [$a, , $conversation] = $this->pair();

        $this->actingAs($a)->post(route('messages.store'), [
            'conversation_id' => $conversation->id,
            'file' => new \Illuminate\Http\UploadedFile(
                storage_path('framework/testing/not-an-image.txt'),
                'notes.txt',
                'text/plain',
                null,
                true
            ),
        ])->assertSessionHasErrors('file');

        $this->assertSame(0, Message::count());
    }

    public function test_the_thread_returns_attachments_and_the_conversation_list(): void
    {
        [$a, $b, $conversation] = $this->pair();
        Message::create(['conversation_id' => $conversation->id, 'user_id' => $b->id, 'body' => 'hello']);

        $this->actingAs($a)->get(route('messages.show', $conversation))
            ->assertOk()
            ->assertInertia(fn ($page) => $page
                ->component('Messages/Show')
                ->has('conversation.messages', 1)
                ->has('conversations', 1)
                ->where('conversations.0.id', $conversation->id));
    }

    public function test_attachments_are_only_visible_to_participants(): void
    {
        [$a, , $conversation] = $this->pair();
        $this->actingAs($a)->post(route('messages.store'), [
            'conversation_id' => $conversation->id,
            'file' => $this->pngUpload('shot.png'),
        ]);

        $outsider = User::factory()->create();
        $this->actingAs($outsider)->get(route('messages.show', $conversation))->assertForbidden();
        $this->actingAs($outsider)->post(route('messages.store'), [
            'conversation_id' => $conversation->id,
            'body' => 'let me in',
        ])->assertForbidden();
    }
}
