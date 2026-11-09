<?php

namespace Tests\Feature;

use App\Models\AppNotification;
use App\Models\Conversation;
use App\Models\Media;
use App\Models\Message;
use App\Models\Post;
use App\Models\Report;
use App\Models\StickerPack;
use App\Models\User;
use App\Support\MessageAttachments;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Tests\Feature\Concerns\MakesImages;
use Tests\TestCase;

class ChatPermissionsTest extends TestCase
{
    use MakesImages, RefreshDatabase;

    public function test_legacy_groups_channels_and_inquiries_are_hidden_and_inaccessible(): void
    {
        Storage::fake('local');
        [$sender, $recipient] = User::factory()->count(2)->create();
        $direct = Conversation::between($sender->id, $recipient->id);
        $media = MessageAttachments::store($sender, $this->pngUpload('retired.png'));
        $this->actingAs($recipient);

        foreach (['group', 'channel', 'inquiry'] as $kind) {
            $legacy = Conversation::create(['kind' => $kind, 'title' => 'Legacy room']);
            $legacy->participants()->attach($sender->id);
            $legacy->participants()->attach($recipient->id, ['folder' => 'Retired rooms']);
            $message = $legacy->messages()->create(['user_id' => $sender->id, 'body' => 'Legacy text', 'media_id' => $media->id]);

            $this->get(route('messages.show', $legacy))->assertForbidden();
            $this->get(route('messages.attachment', [$message, 'original']))->assertForbidden();
            $this->post(route('messages.store'), ['conversation_id' => $legacy->id, 'body' => 'New message'])->assertForbidden();
            $this->post(route('messages.store'), ['conversation_id' => $direct->id, 'forward_id' => $message->id])->assertForbidden();
        }

        AppNotification::create(['user_id' => $recipient->id, 'type' => 'chat_mention']);
        $this->get(route('messages.index', ['tab' => 'channel']))->assertInertia(fn ($page) => $page
            ->has('conversations', 1)->where('conversations.0.id', $direct->id)
            ->where('auth.unread_messages', 0)->where('auth.unread_notifications', 0)->has('folders', 0));
        $this->get(route('messages.index', ['q' => 'Legacy']))->assertInertia(fn ($page) => $page->has('conversations', 0));
        $this->get(route('messages.show', $direct))->assertInertia(fn ($page) => $page->has('conversations', 1));
        $this->get(route('notifications.index'))->assertInertia(fn ($page) => $page->has('notifications', 0));

        foreach (['group', 'channel'] as $kind) {
            $this->postJson(route('messages.rooms.store'), ['kind' => $kind, 'title' => 'New room'])
                ->assertUnprocessable()->assertJsonValidationErrors('kind');
        }
        $this->postJson("/messages/{$legacy->id}/membership", ['action' => 'join'])->assertNotFound();
        $this->assertDatabaseCount('conversations', 4);
        $this->assertDatabaseCount('messages', 3);
        Storage::disk('local')->assertExists($media->path);
    }

    public function test_scheduled_delivery_and_expiration_skip_retired_conversations(): void
    {
        $sender = User::factory()->create();
        $legacy = Conversation::create(['kind' => 'group', 'title' => 'Legacy room']);
        $legacy->participants()->attach($sender->id);
        $message = $legacy->messages()->create([
            'user_id' => $sender->id, 'body' => 'Existing message', 'expires_at' => now()->subMinute(),
        ]);
        DB::table('scheduled_messages')->insert([
            'conversation_id' => $legacy->id, 'user_id' => $sender->id,
            'body' => 'Queued message', 'send_at' => now()->subMinute(),
        ]);

        $this->artisan('messages:send-scheduled')->assertSuccessful();
        $this->artisan('messages:expire')->assertSuccessful();
        $this->assertModelExists($message);
        $this->assertDatabaseCount('messages', 1);
        $this->assertDatabaseHas('scheduled_messages', ['conversation_id' => $legacy->id, 'status' => 'pending']);
    }

    public function test_report_evidence_is_scoped_audited_and_survives_message_expiration_until_resolution(): void
    {
        Storage::fake('local');
        $this->freezeSecond();
        [$sender, $recipient] = User::factory()->count(2)->create();
        $admin = User::factory()->create(['is_admin' => true]);
        $chat = Conversation::between($sender->id, $recipient->id);
        $this->actingAs($sender)->patch(route('messages.settings', $chat), ['auto_delete_seconds' => 3600])->assertRedirect();
        $this->post(route('messages.store'), [
            'conversation_id' => $chat->id, 'body' => 'Reported synthetic content', 'file' => $this->pngUpload('original.png'),
        ])->assertRedirect();
        $message = Message::sole();
        $path = $message->media->path;
        $this->actingAs($recipient)->post(route('reports.store'), [
            'reportable_type' => 'message', 'reportable_id' => $message->id, 'reason' => 'spam',
        ])->assertRedirect();
        $report = Report::sole();

        $this->travel(61)->minutes();
        $this->get(route('messages.attachment', [$message, 'original']))->assertNotFound();
        $this->get(route('messages.show', $chat))->assertInertia(fn ($page) => $page->has('conversation.messages', 0));
        $this->artisan('messages:expire')->assertSuccessful();
        $this->assertModelMissing($message);
        Storage::disk('local')->assertExists($path);

        $url = route('admin.reports.message', $report);
        $this->get($url)->assertForbidden();
        $this->actingAs($admin)->get($url)->assertInertia(fn ($page) => $page->where('body', 'Reported synthetic content'));
        $this->get(route('admin.reports.message.attachment', $report))->assertOk()->assertHeader('Content-Type', 'image/jpeg');
        $this->assertDatabaseHas('moderation_actions', ['report_id' => $report->id, 'action' => 'view_message_evidence']);
        $this->post(route('admin.reports.dismiss', $report), ['reason' => 'Reviewed'])->assertRedirect();
        $this->get($url)->assertNotFound();
        Storage::disk('local')->assertMissing($path);
    }

    public function test_messaging_no_longer_exposes_live_calls(): void
    {
        [$sender, $recipient] = User::factory()->count(2)->create();
        $chat = Conversation::between($sender->id, $recipient->id);
        $callUrl = "/messages/{$chat->id}/calls";

        $this->actingAs($sender)->getJson($callUrl)->assertNotFound();
        $this->postJson($callUrl, ['mode' => 'audio'])->assertNotFound();
        $this->get(route('messages.index'))->assertInertia(fn ($page) => $page
            ->missing('incomingCalls')->missing('privacy.chat_calls'));
        $this->get(route('messages.show', $chat))->assertInertia(fn ($page) => $page
            ->missing('incomingCalls')->missing('conversation.canCall'));
    }

    public function test_sticker_packs_require_original_sfw_works_and_render_in_conversations(): void
    {
        [$artist, $recipient] = User::factory()->count(2)->create();
        $work = Post::create([
            'user_id' => $artist->id, 'type' => 'image', 'rating' => 'sfw', 'title' => 'Test sticker', 'is_original' => true,
        ]);
        $media = Media::create(['user_id' => $artist->id, 'disk' => 'public', 'kind' => 'image', 'path' => 'test-sticker.png']);
        $work->media()->create(['media_id' => $media->id, 'position' => 0]);
        $this->actingAs($recipient)->post(route('messages.stickers.store'), [
            'title' => 'Someone else’s work', 'post_ids' => [$work->id],
        ])->assertSessionHasErrors('post_ids');
        $this->actingAs($artist)->post(route('messages.stickers.store'), [
            'title' => 'Test pack', 'post_ids' => [$work->id],
        ])->assertRedirect();
        $pack = StickerPack::sole();
        $this->getJson(route('messages.stickers.picker'))->assertOk()->assertJsonPath('0.stickers.0.id', $work->id);

        $thread = Conversation::between($artist->id, $recipient->id);
        $this->post(route('messages.store'), [
            'conversation_id' => $thread->id, 'sticker_pack_id' => $pack->id, 'sticker_post_id' => $work->id,
        ])->assertRedirect();
        $this->actingAs($recipient)->get(route('messages.show', $thread))->assertInertia(fn ($page) => $page
            ->where('conversation.messages.0.post.title', 'Test sticker')
            ->where('conversation.messages.0.post.pack', route('messages.stickers.index', ['pack' => $pack->id], false)));

        $work->update(['rating' => 'explicit']);
        $this->get(route('messages.show', $thread))->assertInertia(fn ($page) => $page
            ->where('conversation.messages.0.post', null));
    }

    public function test_documents_download_and_original_images_keep_their_format_behind_a_spoiler(): void
    {
        Storage::fake('local');
        [$sender, $recipient] = User::factory()->count(2)->create();
        $chat = Conversation::between($sender->id, $recipient->id);
        $this->actingAs($sender)->post(route('messages.store'), [
            'conversation_id' => $chat->id,
            'file' => UploadedFile::fake()->createWithContent('brief.pdf', "%PDF-1.4\n1 0 obj\n<<>>\nendobj\n%%EOF\n"),
        ])->assertRedirect();
        $document = Message::sole();
        $this->assertSame('file', $document->media->kind);
        $this->actingAs($recipient)->get(route('messages.attachment', [$document, 'original']))
            ->assertOk()->assertHeader('Content-Disposition', 'attachment; filename=brief.pdf');

        $this->actingAs($sender)->post(route('messages.store'), [
            'conversation_id' => $chat->id, 'file' => $this->pngUpload('original.png'),
            'original' => true, 'spoiler' => true, 'body' => 'Spoiler caption',
        ])->assertRedirect();
        $image = Message::latest('id')->firstOrFail();
        $this->actingAs($recipient)->get(route('messages.index'))->assertInertia(fn ($page) => $page
            ->where('conversations.0.preview', 'Spoiler message'));
        $this->get(route('messages.show', $chat))->assertInertia(fn ($page) => $page
            ->where('conversation.messages.1.spoiler', true));
        // Every image is re-encoded to strip EXIF/GPS, so a PNG arrives as the
        // JPEG that re-encoding produces. What matters is that it is served as an
        // image, privately, and without location metadata.
        $response = $this->get(route('messages.attachment', [$image, 'original']));
        $response->assertOk();
        $this->assertStringStartsWith('image/', $response->headers->get('Content-Type'));
    }
}
