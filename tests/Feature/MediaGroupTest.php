<?php

namespace Tests\Feature;

use App\Models\Conversation;
use App\Models\Message;
use App\Models\User;
use App\Support\MessageAttachments;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Storage;
use Tests\Support\FakeImage;
use Tests\TestCase;

class MediaGroupTest extends TestCase
{
    use RefreshDatabase;

    private function thread(): array
    {
        $a = User::factory()->create(['username' => 'sender']);
        $b = User::factory()->create(['username' => 'receiver']);
        $c = Conversation::between($a->id, $b->id);
        $c->forceFill(['request_status' => 'accepted'])->save();

        return [$a, $b, $c];
    }

    public function test_several_files_arrive_as_one_group(): void
    {
        Storage::fake(MessageAttachments::DISK);
        [$a, , $c] = $this->thread();

        $this->actingAs($a)->post(route('messages.store'), [
            'conversation_id' => $c->id,
            'file' => FakeImage::upload('one.png'),
            'files' => [FakeImage::upload('two.png'), FakeImage::upload('three.png')],
        ])->assertRedirect();

        $messages = Message::orderBy('media_group_position')->get();
        $this->assertCount(3, $messages);
        $this->assertCount(1, $messages->pluck('media_group')->unique(), 'all three share one group id');
        $this->assertSame([0, 1, 2], $messages->pluck('media_group_position')->all());
        // only the first message carries the body
        $this->assertNull($messages[1]->body);
    }

    public function test_a_single_file_is_not_grouped(): void
    {
        Storage::fake(MessageAttachments::DISK);
        [$a, , $c] = $this->thread();

        $this->actingAs($a)->post(route('messages.store'), [
            'conversation_id' => $c->id,
            'file' => FakeImage::upload('solo.png'),
        ])->assertRedirect();

        $this->assertNull(Message::sole()->media_group);
    }

    public function test_the_group_id_reaches_the_thread_payload(): void
    {
        Storage::fake(MessageAttachments::DISK);
        [$a, $b, $c] = $this->thread();

        $this->actingAs($a)->post(route('messages.store'), [
            'conversation_id' => $c->id,
            'file' => FakeImage::upload('one.png'),
            'files' => [FakeImage::upload('two.png')],
        ]);

        $this->actingAs($b)->get(route('messages.show', $c))
            ->assertOk()
            ->assertInertia(fn ($page) => $page
                ->has('conversation.messages', 2)
                ->where('conversation.messages.0.media_group_position', 0)
                ->has('conversation.messages.0.media_group'));
    }

    public function test_at_most_nine_extra_files_are_accepted(): void
    {
        Storage::fake(MessageAttachments::DISK);
        [$a, , $c] = $this->thread();

        $extra = [];
        for ($i = 0; $i < 10; $i++) {
            $extra[] = FakeImage::upload("x{$i}.png");
        }

        $this->actingAs($a)->post(route('messages.store'), [
            'conversation_id' => $c->id,
            'file' => FakeImage::upload('lead.png'),
            'files' => $extra,
        ])->assertSessionHasErrors('files');
    }
}
