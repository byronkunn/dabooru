<?php

namespace Tests\Feature;

use App\Models\Conversation;
use App\Models\Message;
use App\Models\StickerPack;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class StickerReplyTest extends TestCase
{
    use RefreshDatabase;

    private function thread(): array
    {
        $a = User::factory()->create(['username' => 'stickerer']);
        $b = User::factory()->create(['username' => 'other']);
        $c = Conversation::between($a->id, $b->id);
        $c->forceFill(['request_status' => 'accepted'])->save();

        return [$a, $b, $c];
    }

    public function test_a_sticker_can_be_sent_as_a_reply(): void
    {
        [$a, , $c] = $this->thread();
        $original = $c->messages()->create(['user_id' => $a->id, 'body' => 'Reply to me']);

        // a sticker is an eligible SFW original image post from the pack
        $stickerPost = \App\Models\Post::create([
            'user_id' => $a->id, 'type' => 'image', 'rating' => 'sfw',
            'title' => 'Sticker', 'is_original' => true,
        ]);
        // eligibility needs image media attached to the post
        $media = \App\Models\Media::create([
            'user_id' => $a->id, 'disk' => 'public', 'path' => 'sticker.png',
            'kind' => 'image', 'status' => 'ready', 'mime_type' => 'image/png',
        ]);
        \App\Models\PostMedia::create(['post_id' => $stickerPost->id, 'media_id' => $media->id, 'position' => 0]);

        $pack = StickerPack::create([
            'user_id' => $a->id, 'title' => 'My stickers', 'is_public' => true,
            'post_ids' => [$stickerPost->id],
        ]);

        $this->actingAs($a)->post(route('messages.store'), [
            'conversation_id' => $c->id,
            'reply_to_id' => $original->id,
            'sticker_pack_id' => $pack->id,
            'sticker_post_id' => $stickerPost->id,
        ])->assertRedirect();

        $sticker = Message::where('id', '!=', $original->id)->latest('id')->first();
        $this->assertSame($original->id, $sticker->reply_to_id, 'the sticker should carry the reply link');
    }
}
