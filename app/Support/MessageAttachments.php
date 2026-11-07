<?php

namespace App\Support;

use App\Models\Media;
use App\Models\Message;
use App\Models\User;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use RuntimeException;
use Throwable;

class MessageAttachments
{
    public const DISK = 'local';

    public static function isPrivate(Media $media): bool
    {
        return $media->disk === self::DISK && str_starts_with($media->path, 'dm/');
    }

    public static function store(User $sender, UploadedFile $file): Media
    {
        $directory = "dm/{$sender->id}/".bin2hex(random_bytes(16));
        $disk = Storage::disk(self::DISK);
        $isVideo = str_starts_with((string) $file->getMimeType(), 'video/');

        try {
            $path = $isVideo
                ? $file->store($directory, self::DISK)
                : ImageProcessor::storeClean($file->getRealPath(), $directory, self::DISK);
            $thumb = $isVideo ? null : ImageProcessor::storeThumbnail($file->getRealPath(), $directory, self::DISK);
            if (! $path || ! $disk->exists($path) || ($thumb && ! $disk->exists($thumb))) {
                throw new RuntimeException('Could not store the message attachment.');
            }

            [$width, $height] = ImageProcessor::dimensions($file->getRealPath());

            return Media::create([
                'user_id' => $sender->id,
                'disk' => self::DISK,
                'path' => $path,
                'thumb_path' => $thumb,
                'kind' => $isVideo ? 'video' : 'image',
                'width' => $width,
                'height' => $height,
                'size_bytes' => $file->getSize(),
                'status' => 'ready',
                'mime_type' => $disk->mimeType($path),
            ]);
        } catch (Throwable $error) {
            $disk->deleteDirectory($directory);
            throw $error;
        }
    }

    public static function purgeAfterCommit(?int $mediaId): void
    {
        if ($mediaId === null) {
            return;
        }

        DB::afterCommit(fn () => rescue(fn () => DB::transaction(function () use ($mediaId) {
            $media = Media::lockForUpdate()->find($mediaId);
            if (! $media || (! self::isPrivate($media) && ! self::isLegacy($media)) || self::inUse($media)) {
                return;
            }

            self::deleteFiles($media);
            $media->delete();
        })));
    }

    public static function isLegacy(Media $media): bool
    {
        return preg_match('~^uploads/[0-9]+/dm/[a-zA-Z0-9][a-zA-Z0-9_.-]*$~D', $media->path) === 1
            && ($media->thumb_path === null
                || (dirname($media->thumb_path) === dirname($media->path).'/thumbs'
                    && preg_match('/^[a-zA-Z0-9_-]+[.]jpg$/D', basename($media->thumb_path)) === 1));
    }

    public static function privatizeLegacy(int $mediaId): string
    {
        return DB::transaction(function () use ($mediaId) {
            $media = Media::lockForUpdate()->find($mediaId);
            if (! $media || ! self::isLegacy($media)) {
                return 'skipped';
            }
            if (self::usedOutsideMessaging($media)) {
                return 'shared';
            }
            if (! Message::where('media_id', $media->id)->exists()) {
                self::deleteFiles($media);
                $media->delete();

                return 'removed';
            }

            $directory = 'dm/legacy/'.$media->id;
            $path = $directory.'/original';
            $thumb = $media->thumb_path ? $directory.'/thumb.jpg' : null;
            self::copyLegacyFile($media, $media->path, $path);
            if ($thumb) {
                self::copyLegacyFile($media, $media->thumb_path, $thumb);
            }

            // Revoke the old URL only after every private copy is ready. If a
            // retry finds an old file gone, it can use the verified private copy.
            self::deleteFiles($media);
            $media->forceFill(['disk' => self::DISK, 'path' => $path, 'thumb_path' => $thumb])->save();

            return 'moved';
        });
    }

    private static function copyLegacyFile(Media $media, string $oldPath, string $newPath): void
    {
        $source = Storage::disk($media->disk);
        $private = Storage::disk(self::DISK);
        if (! $source->exists($oldPath)) {
            if (! $private->exists($newPath)) {
                throw new RuntimeException('A legacy attachment file is missing.');
            }

            return;
        }

        $stream = $source->readStream($oldPath);
        if (! is_resource($stream)) {
            throw new RuntimeException('Could not read a legacy attachment.');
        }

        try {
            if (! $private->put($newPath, $stream) || $private->size($newPath) !== $source->size($oldPath)) {
                throw new RuntimeException('Could not verify the private attachment copy.');
            }
        } finally {
            fclose($stream);
        }
    }

    private static function deleteFiles(Media $media): void
    {
        $disk = Storage::disk($media->disk);
        foreach (array_filter([$media->path, $media->thumb_path]) as $path) {
            if ($disk->exists($path) && ! $disk->delete($path)) {
                throw new RuntimeException('Could not remove an obsolete message attachment.');
            }
        }
    }

    private static function inUse(Media $media): bool
    {
        return Message::where('media_id', $media->id)->exists() || self::usedOutsideMessaging($media);
    }

    private static function usedOutsideMessaging(Media $media): bool
    {
        return DB::table('post_media')->where('media_id', $media->id)->exists()
            || DB::table('comic_pages')->where('media_id', $media->id)->exists()
            || DB::table('comics')->where('cover_media_id', $media->id)->exists()
            || Media::whereKeyNot($media->id)->where('disk', $media->disk)
                ->where(fn ($q) => $q->whereIn('path', array_filter([$media->path, $media->thumb_path]))
                    ->orWhereIn('thumb_path', array_filter([$media->path, $media->thumb_path])))->exists();
    }
}
