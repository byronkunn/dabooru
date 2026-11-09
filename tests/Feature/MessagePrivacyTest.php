<?php

namespace Tests\Feature;

use App\Models\Conversation;
use App\Models\Media;
use App\Models\Message;
use App\Models\User;
use App\Support\MessageAttachments;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Storage;
use Tests\Support\FakeImage;
use Tests\TestCase;

class MessagePrivacyTest extends TestCase
{
    use RefreshDatabase;

    private function conversation(): array
    {
        $a = User::factory()->create(['username' => 'sender']);
        $b = User::factory()->create(['username' => 'recipient']);

        return [$a, $b, Conversation::between($a->id, $b->id)];
    }

    public function test_images_are_reencoded_even_when_sent_as_original(): void
    {
        Storage::fake(MessageAttachments::DISK);
        [$sender] = $this->conversation();

        $upload = FakeImage::jpegWithExif();
        // sanity: the fixture really carries an EXIF segment
        $this->assertStringContainsString('Exif', file_get_contents($upload->getRealPath()));

        $media = MessageAttachments::store($sender, $upload, true);

        $stored = Storage::disk(MessageAttachments::DISK)->get($media->path);
        $this->assertStringNotContainsString('Exif', $stored, 'EXIF must be stripped even for "original" sends');
        $this->assertStringNotContainsString('GPSLatitude', $stored, 'GPS data must never reach storage');
    }

    public function test_a_normal_send_also_strips_location_data(): void
    {
        Storage::fake(MessageAttachments::DISK);
        [$sender] = $this->conversation();

        $media = MessageAttachments::store($sender, FakeImage::jpegWithExif());

        $stored = Storage::disk(MessageAttachments::DISK)->get($media->path);
        $this->assertStringNotContainsString('GPSLatitude', $stored);
    }
}
