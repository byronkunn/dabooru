<?php

namespace App\Support;

use Illuminate\Support\Facades\Storage;

class ImageProcessor
{
    /**
     * Read [width, height] without loading a full GD context when possible.
     *
     * @return array{0:?int,1:?int}
     */
    public static function dimensions(?string $path): array
    {
        if (! $path || ! is_file($path)) {
            return [null, null];
        }

        $info = @getimagesize($path);

        return $info ? [$info[0], $info[1]] : [null, null];
    }

    /**
     * Re-encode an image and drop every metadata block (EXIF, GPS, XMP, IPTC).
     *
     * On an adult site the EXIF of a phone photo can carry the poster's home
     * coordinates, so metadata is stripped before the file is stored. Falls
     * back to the original bytes when re-encoding is not possible (no GD, or
     * a format GD cannot read), so an upload is never lost.
     *
     * @return array{0:string,1:?string} [bytes, mime extension] or [original raw path, null]
     */
    public static function stripMetadata(string $sourcePath): array
    {
        if (! function_exists('imagecreatefromstring')) {
            return [file_get_contents($sourcePath), null];
        }

        $raw = @file_get_contents($sourcePath);
        if ($raw === false) {
            return ['', null];
        }

        $image = @imagecreatefromstring($raw);
        if (! $image) {
            return [$raw, null];
        }

        $width = imagesx($image);
        $height = imagesy($image);

        // keep a sane ceiling so a huge scan cannot exhaust memory
        $max = 4000;
        if ($width > $max || $height > $max) {
            $scale = $max / max($width, $height);
            $resized = imagescale($image, (int) ($width * $scale), (int) ($height * $scale));
            if ($resized) {
                imagedestroy($image);
                $image = $resized;
            }
        }

        ob_start();
        imagejpeg($image, null, 88);
        $clean = ob_get_clean();
        imagedestroy($image);

        if (! is_string($clean) || $clean === '') {
            return [$raw, null];
        }

        return [$clean, 'jpg'];
    }

    /**
     * Write a metadata-stripped copy of an uploaded file and return its path.
     */
    public static function storeClean(string $sourcePath, string $directory, string $disk): string
    {
        [$bytes, $ext] = self::stripMetadata($sourcePath);
        $name = $directory.'/'.bin2hex(random_bytes(12)).($ext ? '.'.$ext : '');
        Storage::disk($disk)->put($name, $bytes);

        return $name;
    }
}
