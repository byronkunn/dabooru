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
        Storage::fake('local');
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

    public function test_only_participants_can_open_or_send_to_a_thread_with_attachments(): void
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

    public function test_attachment_urls_check_membership_and_stop_working_after_removal(): void
    {
        [$sender, $recipient, $thread] = $this->pair();
        $outsider = User::factory()->create();
        $this->actingAs($sender)->post(route('messages.store'), [
            'conversation_id' => $thread->id,
            'file' => $this->pngUpload('private.png', 300, 200),
        ])->assertRedirect();
        $message = Message::sole();
        $media = $message->media;
        $url = route('messages.attachment', [$message, 'original'], false);
        $thumb = route('messages.attachment', [$message, 'thumb'], false);

        $this->assertSame('local', $media->disk);
        Storage::disk('local')->assertExists([$media->path, $media->thumb_path]);
        Storage::disk('public')->assertMissing($media->path);
        $this->actingAs($recipient)->get(route('messages.show', $thread))->assertInertia(fn ($page) => $page
            ->where('conversation.messages.0.media.url', $url)->where('conversation.messages.0.media.thumb', $thumb)
            ->where('conversations.0.preview', 'Sent an attachment'));
        $this->get($url)->assertOk()->assertHeader('X-Content-Type-Options', 'nosniff');
        $this->get($thumb)->assertOk();
        $this->get($url, ['Range' => 'bytes=0-7'])->assertStatus(206);
        $this->actingAs($outsider)->get($url)->assertForbidden();
        $this->get($thumb)->assertForbidden();

        $this->actingAs($sender)->delete(route('messages.destroy', $message))->assertRedirect();
        $this->actingAs($recipient)->get($url)->assertNotFound();
        $this->get($thumb)->assertNotFound();
        Storage::disk('local')->assertMissing([$media->path, $media->thumb_path]);
        $this->assertDatabaseMissing('media', ['id' => $media->id]);
    }

    public function test_backfill_moves_referenced_files_and_removes_detached_public_files(): void
    {
        [$sender, $recipient, $thread] = $this->pair();
        $path = "uploads/{$sender->id}/dm/legacy.jpg";
        $thumb = "uploads/{$sender->id}/dm/thumbs/legacy.jpg";
        $orphan = "uploads/{$sender->id}/dm/detached.jpg";
        Storage::disk('public')->put($path, 'original bytes');
        Storage::disk('public')->put($thumb, 'thumb bytes');
        Storage::disk('public')->put($orphan, 'detached bytes');
        $media = Media::create(['disk' => 'public', 'path' => $path, 'thumb_path' => $thumb, 'kind' => 'image']);
        $detached = Media::create(['disk' => 'public', 'path' => $orphan, 'kind' => 'image']);
        $message = $thread->messages()->create(['user_id' => $sender->id, 'media_id' => $media->id]);

        $this->artisan('messages:privatize-attachments --dry-run')->assertSuccessful();
        Storage::disk('public')->assertExists([$path, $thumb, $orphan]);
        $this->artisan('messages:privatize-attachments')->assertSuccessful();
        $this->artisan('messages:privatize-attachments')->assertSuccessful();
        $media->refresh();

        Storage::disk('public')->assertMissing([$path, $thumb, $orphan]);
        $this->assertDatabaseMissing('media', ['id' => $detached->id]);
        $this->assertSame('local', $media->disk);
        $this->assertSame('original bytes', Storage::disk('local')->get($media->path));
        $this->assertSame('thumb bytes', Storage::disk('local')->get($media->thumb_path));
        $this->actingAs($recipient)->get(route('messages.attachment', [$message, 'original']))->assertOk();
    }
}
