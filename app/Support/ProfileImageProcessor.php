<?php

namespace App\Support;

use Illuminate\Validation\ValidationException;

class ProfileImageProcessor
{
    /**
     * Crop a user supplied image to a fixed, optimized profile asset.
     * Crop coordinates are normalized fractions of the source image.
     *
     * @param array{x:float|int,y:float|int,width:float|int,height:float|int} $crop
     * @return array{bytes:string,extension:string,mime:string}
     */
    public static function crop(string $sourcePath, string $type, array $crop): array
    {
        if (! function_exists('imagecreatefromstring')) {
            throw ValidationException::withMessages(['crop' => 'Image editing is temporarily unavailable. Please try again later.']);
        }

        $dimensions = @getimagesize($sourcePath);
        if (! $dimensions || ((int) $dimensions[0] * (int) $dimensions[1]) > 30000000) {
            throw ValidationException::withMessages([$type => 'Choose an image with a resolution under 30 megapixels.']);
        }

        $bytes = @file_get_contents($sourcePath);
        $source = is_string($bytes) ? @imagecreatefromstring($bytes) : false;
        if (! $source) {
            throw ValidationException::withMessages([$type => 'This image could not be opened. Please choose another image.']);
        }

        if (function_exists('exif_read_data')) {
            $orientation = @exif_read_data($sourcePath)['Orientation'] ?? 1;
            $rotation = match ($orientation) {
                3 => 180,
                5, 6 => -90,
                7, 8 => 90,
                default => 0,
            };
            if ($rotation !== 0 && ($rotated = imagerotate($source, $rotation, 0))) {
                imagedestroy($source);
                $source = $rotated;
            }
            if (in_array($orientation, [2, 5, 7], true)) {
                imageflip($source, IMG_FLIP_HORIZONTAL);
            } elseif ($orientation === 4) {
                imageflip($source, IMG_FLIP_VERTICAL);
            }
        }

        $sourceWidth = imagesx($source);
        $sourceHeight = imagesy($source);
        $x = max(0.0, min(1.0, (float) $crop['x']));
        $y = max(0.0, min(1.0, (float) $crop['y']));
        $width = max(0.001, min(1.0 - $x, (float) $crop['width']));
        $height = max(0.001, min(1.0 - $y, (float) $crop['height']));
        [$targetWidth, $targetHeight] = $type === 'avatar' ? [512, 512] : [1500, 500];
        $targetRatio = $targetWidth / $targetHeight;
        $cropRatio = ($width * $sourceWidth) / ($height * $sourceHeight);
        if ($cropRatio > $targetRatio) {
            $width = $height * $sourceHeight * $targetRatio / $sourceWidth;
            $x = min(1.0 - $width, max(0.0, $x + (((float) $crop['width'] - $width) / 2)));
        } elseif ($cropRatio < $targetRatio) {
            $height = $width * $sourceWidth / $targetRatio / $sourceHeight;
            $y = min(1.0 - $height, max(0.0, $y + (((float) $crop['height'] - $height) / 2)));
        }
        $sourceX = (int) floor($x * $sourceWidth);
        $sourceY = (int) floor($y * $sourceHeight);
        $cropWidth = max(1, min($sourceWidth - $sourceX, (int) round($width * $sourceWidth)));
        $cropHeight = max(1, min($sourceHeight - $sourceY, (int) round($height * $sourceHeight)));

        $output = imagecreatetruecolor($targetWidth, $targetHeight);
        imagealphablending($output, false);
        imagesavealpha($output, true);
        $transparent = imagecolorallocatealpha($output, 0, 0, 0, 127);
        imagefill($output, 0, 0, $transparent);
        imagecopyresampled($output, $source, 0, 0, $sourceX, $sourceY, $targetWidth, $targetHeight, $cropWidth, $cropHeight);

        ob_start();
        if (function_exists('imagewebp')) {
            imagewebp($output, null, 86);
            $extension = 'webp';
            $mime = 'image/webp';
        } else {
            imagepng($output, null, 7);
            $extension = 'png';
            $mime = 'image/png';
        }
        $result = ob_get_clean();
        imagedestroy($source);
        imagedestroy($output);

        if (! is_string($result) || $result === '') {
            throw ValidationException::withMessages(['crop' => 'The edited image could not be saved. Please try again.']);
        }

        return ['bytes' => $result, 'extension' => $extension, 'mime' => $mime];
    }
}
