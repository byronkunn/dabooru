<?php

namespace App\Jobs;

use App\Models\Media;
use App\Models\Post;
use App\Models\Tag;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;
use Symfony\Component\Process\Process;
use Throwable;

class ProcessVideoUpload implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $tries = 1;
    public int $timeout = 5400;
    public bool $failOnTimeout = true;

    public function __construct(public int $mediaId, public int $postId, public bool $publishWhenReady = true)
    {
        $this->onQueue('media');
    }

    public function handle(): void
    {
        $media = Media::findOrFail($this->mediaId);
        $post = Post::withoutGlobalScope('published')->findOrFail($this->postId);
        abort_unless($media->kind === 'video' && $media->disk === 'local' && str_starts_with($media->path, 'drafts/'), 422);

        $input = Storage::disk('local')->path($media->path);
        if (! is_file($input)) {
            throw new \RuntimeException('The private source video is missing.');
        }

        $workDir = storage_path('app/private/video-processing/'.$media->id.'-'.bin2hex(random_bytes(6)));
        if (! mkdir($workDir, 0700, true) && ! is_dir($workDir)) {
            throw new \RuntimeException('Could not create the video processing directory.');
        }
        $output = $workDir.'/ready.mp4';

        try {
            $probe = new Process([
                config('video_processing.ffprobe'), '-v', 'error', '-show_entries',
                'format=duration:stream=codec_type,width,height', '-of', 'json', $input,
            ]);
            $probe->setTimeout(60);
            $probe->mustRun();
            $info = json_decode($probe->getOutput(), true, flags: JSON_THROW_ON_ERROR);
            $video = collect($info['streams'] ?? [])->firstWhere('codec_type', 'video');
            $duration = (float) ($info['format']['duration'] ?? 0);
            if (! $video || $duration <= 0 || $duration > config('video_processing.max_duration_seconds')) {
                throw new \RuntimeException('The video is invalid or exceeds the supported duration.');
            }
            if ((int) ($video['width'] ?? 0) > 8192 || (int) ($video['height'] ?? 0) > 8192
                || ((int) ($video['width'] ?? 0) * (int) ($video['height'] ?? 0)) > 33554432) {
                throw new \RuntimeException('The video dimensions exceed the supported limit.');
            }

            $transcode = new Process([
                config('video_processing.ffmpeg'), '-nostdin', '-hide_banner', '-loglevel', 'error', '-y',
                '-i', $input,
                '-map', '0:v:0', '-map', '0:a:0?',
                '-map_metadata', '-1', '-map_metadata:s', '-1', '-map_chapters', '-1',
                '-c:v', 'libx264', '-preset', config('video_processing.preset'), '-crf', '23',
                '-pix_fmt', 'yuv420p', '-c:a', 'aac', '-b:a', '192k',
                '-movflags', '+faststart', '-f', 'mp4', $output,
            ]);
            $transcode->setTimeout($this->timeout - 120);
            $transcode->mustRun();

            if (! is_file($output) || filesize($output) < 1024) {
                throw new \RuntimeException('The video processor did not produce a playable file.');
            }

            $verify = new Process([
                config('video_processing.ffprobe'), '-v', 'error', '-show_entries',
                'format=duration:stream=codec_type,codec_name,width,height', '-of', 'json', $output,
            ]);
            $verify->setTimeout(60);
            $verify->mustRun();
            $result = json_decode($verify->getOutput(), true, flags: JSON_THROW_ON_ERROR);
            $outputVideo = collect($result['streams'] ?? [])->firstWhere('codec_type', 'video');
            $outputDuration = (float) ($result['format']['duration'] ?? 0);
            if (! $outputVideo || $outputVideo['codec_name'] !== 'h264' || $outputDuration <= 0
                || ! isset($outputVideo['width'], $outputVideo['height'])) {
                throw new \RuntimeException('The processed video failed playback validation.');
            }

            $disk = config('filesystems.upload_disk', 'public');
            $finalPath = 'uploads/'.$media->user_id.'/'.bin2hex(random_bytes(20)).'.mp4';
            $stream = fopen($output, 'rb');
            try {
                $stored = Storage::disk($disk)->put($finalPath, $stream, ['ContentType' => 'video/mp4']);
            } finally {
                if (is_resource($stream)) {
                    fclose($stream);
                }
            }
            if (! $stored || ! Storage::disk($disk)->exists($finalPath)) {
                throw new \RuntimeException('Could not store the processed video.');
            }

            try {
                $oldPath = DB::transaction(function () use ($media, $post, $disk, $finalPath, $outputVideo, $outputDuration, $output) {
                    $lockedMedia = Media::lockForUpdate()->findOrFail($media->id);
                    $lockedPost = Post::withoutGlobalScope('published')->lockForUpdate()->findOrFail($post->id);
                    if (! $lockedPost->is_draft || $lockedMedia->status !== 'processing') {
                        throw new \RuntimeException('The video upload is no longer awaiting processing.');
                    }

                    $oldPath = $lockedMedia->path;
                    $lockedMedia->forceFill([
                        'disk' => $disk,
                        'path' => $finalPath,
                        'thumb_path' => null,
                        'width' => (int) $outputVideo['width'],
                        'height' => (int) $outputVideo['height'],
                        'duration_seconds' => (int) round($outputDuration),
                        'size_bytes' => filesize($output),
                        'mime_type' => 'video/mp4',
                        'status' => 'ready',
                    ])->save();

                    if ($this->publishWhenReady) {
                        Tag::syncFor($lockedPost, $lockedPost->draft_tags ?? []);
                        $lockedPost->forceFill([
                            'is_draft' => false,
                            'draft_tags' => null,
                            'created_at' => now()->utc(),
                        ])->save();
                    }


                    return $oldPath;
                });
                Storage::disk('local')->delete($oldPath);
            } catch (Throwable $exception) {
                Storage::disk($disk)->delete($finalPath);
                throw $exception;
            }
        } finally {
            if (is_dir($workDir)) {
                foreach (glob($workDir.'/*') ?: [] as $file) {
                    @unlink($file);
                }
                @rmdir($workDir);
            }
        }
    }

    public function failed(Throwable $exception): void
    {
        Media::whereKey($this->mediaId)->where('status', 'processing')->update(['status' => 'failed']);
        Log::error('Video upload processing failed.', [
            'media_id' => $this->mediaId,
            'post_id' => $this->postId,
            'exception' => $exception->getMessage(),
        ]);
    }
}
