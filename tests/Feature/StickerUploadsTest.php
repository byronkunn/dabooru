<?php

namespace Tests\Feature;

use App\Models\Conversation;
use App\Models\Message;
use App\Models\StickerPack;
use App\Models\User;
use App\Support\MessageAttachments;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Tests\Feature\Concerns\MakesImages;
use Tests\TestCase;

class StickerUploadsTest extends TestCase
{
    use MakesImages, RefreshDatabase;

    public function test_catalog_preserves_originating_chat_through_search_creation_and_deletion(): void
    {
        Storage::fake('local');
        [$owner, $recipient] = User::factory()->count(2)->create();
        $chat = Conversation::between($owner->id, $recipient->id);
        $this->actingAs($owner)->get(route('messages.stickers.index', ['q' => 'Wave', 'return_chat' => $chat->id]))
            ->assertInertia(fn ($page) => $page->where('filters.return_chat', (string) $chat->id)->where('filters.q', 'Wave'));
        $this->post(route('messages.stickers.store', ['return_chat' => $chat->id]), [
            'title' => 'Wave', 'files' => [$this->pngUpload()],
        ])->assertRedirect(route('messages.stickers.index', ['pack' => StickerPack::sole()->id, 'return_chat' => $chat->id]));
        $this->delete(route('messages.stickers.destroy', [
            'stickerPack' => StickerPack::sole()->id, 'return_chat' => $chat->id,
        ]))->assertRedirect(route('messages.stickers.index', ['return_chat' => $chat->id]));
        $this->get(route('messages.stickers.index', ['return_chat' => 'https://example.com']))
            ->assertSessionHasErrors('return_chat');
    }

    public function test_private_multiple_uploads_preserve_files_and_only_owner_can_use_pack(): void
    {
        Storage::fake('local');
        [$owner, $recipient, $outsider] = User::factory()->count(3)->create();
        $gif = base64_decode('R0lGODlhAQABAIAAAAAAAP///yH5BAEAAAAALAAAAAABAAEAAAIBRAA7');
        $this->actingAs($owner)->post(route('messages.stickers.store'), [
            'title' => 'Private reactions', 'is_public' => false,
            'files' => [\Tests\Support\FakeImage::transparentPng('wave.png'), UploadedFile::fake()->createWithContent('dance.gif', $gif)],
        ])->assertRedirect();
        $pack = StickerPack::sole();
        $this->assertFalse($pack->is_public);
        $this->assertDatabaseCount('posts', 0);
        $this->assertCount(2, $pack->stickers);
        $sticker = $pack->stickers->last();
        $image = route('messages.stickers.image', $sticker);
        $this->get($image)->assertOk()->assertHeader('Content-Type', 'image/gif');
        $this->assertSame($gif, Storage::disk('local')->get($sticker->media->path));
        $this->getJson(route('messages.stickers.picker'))->assertJsonCount(1)
            ->assertJsonPath('0.stickers.1.upload_id', $sticker->id);

        $chat = Conversation::between($owner->id, $recipient->id);
        $payload = ['conversation_id' => $chat->id, 'sticker_pack_id' => $pack->id, 'sticker_upload_id' => $sticker->id];
        $this->actingAs($recipient)->get($image)->assertNotFound();
        $this->getJson(route('messages.stickers.picker'))->assertExactJson([]);
        $this->get(route('messages.stickers.index', ['pack' => $pack->id]))->assertInertia(fn ($page) => $page->has('packs', 0));
        $this->post(route('messages.store'), $payload)->assertNotFound();
        $this->patch(route('messages.stickers.update', $pack), ['is_public' => true])->assertForbidden();
        $this->actingAs($owner)->post(route('messages.store'), $payload)->assertRedirect();
        $message = Message::sole();
        $attachment = route('messages.attachment', [$message, 'original']);
        $this->actingAs($recipient)->get($attachment)->assertOk()->assertHeader('Content-Type', 'image/gif');
        $this->get(route('messages.show', $chat))->assertInertia(fn ($page) => $page
            ->where('conversation.messages.0.media.sticker', true)->where('conversation.messages.0.media.thumb', null));
        $this->actingAs($outsider)->get($attachment)->assertForbidden();

        $this->actingAs($owner)->delete(route('messages.stickers.destroy', $pack))->assertRedirect();
        $this->actingAs($recipient)->get($attachment)->assertOk();
        Storage::disk('local')->assertExists($sticker->media->path);
    }

    public function test_publishing_search_and_reprivatizing_apply_to_catalog_images_and_sends(): void
    {
        Storage::fake('local');
        [$owner, $viewer] = User::factory()->count(2)->create();
        $this->actingAs($owner)->post(route('messages.stickers.store'), [
            'title' => 'Moon dance', 'is_public' => true, 'files' => [\Tests\Support\FakeImage::transparentPng('wave.png')],
        ])->assertRedirect();
        $pack = StickerPack::sole();
        $sticker = $pack->stickers()->sole();
        $this->actingAs($viewer)->getJson(route('messages.stickers.picker', ['q' => 'Moon']))->assertJsonCount(1);
        $this->getJson(route('messages.stickers.picker', ['q' => $owner->username]))->assertJsonCount(1);
        $this->getJson(route('messages.stickers.picker', ['q' => 'wave']))->assertJsonCount(1);
        $this->getJson(route('messages.stickers.picker', ['q' => 'Missing']))->assertExactJson([]);
        $this->get(route('messages.stickers.index', ['q' => 'Moon']))->assertInertia(fn ($page) => $page->has('packs', 1));
        $this->get(route('messages.stickers.image', $sticker))->assertOk()->assertHeader('Content-Type', 'image/png');
        $chat = Conversation::between($owner->id, $viewer->id);
        $payload = ['conversation_id' => $chat->id, 'sticker_pack_id' => $pack->id, 'sticker_upload_id' => $sticker->id];
        $this->post(route('messages.store'), $payload)->assertRedirect();
        $this->actingAs($owner)->patch(route('messages.stickers.update', $pack), ['is_public' => false])->assertRedirect();
        $this->actingAs($viewer)->getJson(route('messages.stickers.picker'))->assertExactJson([]);
        $this->get(route('messages.stickers.image', $sticker))->assertNotFound();
        $this->post(route('messages.store'), $payload)->assertNotFound();
        $this->get(route('messages.attachment', [Message::sole(), 'original']))->assertOk();
        $this->actingAs($owner)->patch(route('messages.stickers.update', $pack), ['is_public' => true])->assertRedirect();
        $this->post(route('users.block', $viewer->username))->assertRedirect();
        $this->actingAs($viewer)->getJson(route('messages.stickers.picker'))->assertExactJson([]);
        $this->get(route('messages.stickers.image', $sticker))->assertNotFound();
    }

    public function test_upload_validation_and_request_gate_cannot_be_bypassed(): void
    {
        Storage::fake('local');
        [$owner, $recipient] = User::factory()->count(2)->create();
        $this->actingAs($owner);
        foreach ([
            ['files' => [UploadedFile::fake()->create('bad.svg', 1, 'image/svg+xml')]],
            ['files' => [$this->pngUpload('large.png', 2049, 1)]],
            ['files' => array_fill(0, 17, $this->pngUpload())],
            ['files' => [UploadedFile::fake()->image('oversize.png')->size(5121)]],
            ['files' => []],
        ] as $invalid) {
            $this->post(route('messages.stickers.store'), ['title' => 'Invalid'] + $invalid)->assertSessionHasErrors();
        }
        $this->assertDatabaseCount('sticker_packs', 0);
        $this->assertDatabaseCount('media', 0);
        $this->post(route('messages.stickers.store'), [
            'title' => 'Valid', 'files' => [$this->pngUpload()],
        ])->assertRedirect();
        $pack = StickerPack::sole();
        $this->get(route('messages.index', ['to' => $recipient->username]))->assertRedirect();
        $chat = Conversation::sole();
        $this->post(route('messages.store'), [
            'conversation_id' => $chat->id, 'sticker_pack_id' => $pack->id,
            'sticker_upload_id' => $pack->stickers()->sole()->id,
        ])->assertSessionHasErrors('body');
        $this->assertDatabaseCount('messages', 0);

        $media = $pack->stickers()->sole()->media;
        MessageAttachments::purgeAfterCommit($media->id);
        $this->assertModelExists($media);
        Storage::disk('local')->assertExists($media->path);
    }
}
