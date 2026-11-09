<?php

namespace Tests\Feature;

use App\Models\Conversation;
use App\Models\User;
use App\Support\MessageAttachments;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class MessagePosterTest extends TestCase
{
    use RefreshDatabase;

    public function test_a_dm_video_gets_a_poster_when_ffmpeg_is_available(): void
    {
        $ffmpeg = trim((string) @shell_exec('command -v ffmpeg 2>/dev/null'));
        if ($ffmpeg === '') {
            $this->markTestSkipped('ffmpeg is not installed on this machine.');
        }

        Storage::fake(MessageAttachments::DISK);
        $sender = User::factory()->create();

        // build a tiny real video so ffmpeg has something to extract a frame from
        $path = storage_path('framework/testing/poster-'.uniqid().'.mp4');
        @exec(sprintf('%s -y -loglevel error -f lavfi -i color=c=purple:s=64x64:d=1 -pix_fmt yuv420p %s 2>/dev/null',
            escapeshellarg($ffmpeg), escapeshellarg($path)), $o, $status);
        if ($status !== 0 || ! is_file($path)) {
            $this->markTestSkipped('could not generate a test video.');
        }

        $upload = new UploadedFile($path, 'clip.mp4', 'video/mp4', null, true);
        $media = MessageAttachments::store($sender, $upload);

        $this->assertSame('video', $media->kind);
        $this->assertNotNull($media->thumb_path, 'a video attached to a DM should get a poster frame');
        Storage::disk(MessageAttachments::DISK)->assertExists($media->thumb_path);
    }
}
