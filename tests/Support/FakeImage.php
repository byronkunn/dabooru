<?php

namespace Tests\Support;

use Illuminate\Http\UploadedFile;

/**
 * Minimal, dependency-free image upload for tests.
 *
 * build PNG bytes by hand so the test suite does not rely on GD or on a
 * writable system temp directory.
 */
final class FakeImage
{
    public static function upload(string $name = 'image.png', int $width = 10, int $height = 10): UploadedFile
    {
        $dir = storage_path('framework/testing');
        if (! is_dir($dir)) {
            mkdir($dir, 0777, true);
        }

        $path = $dir.'/'.uniqid('img-', true).'.png';
        file_put_contents($path, self::png($width, $height));

        return new UploadedFile($path, $name, 'image/png', null, true);
    }

    public static function png(int $width, int $height): string
    {
        // IHDR: 8-bit RGB
        $ihdr = pack('NNCCCCC', $width, $height, 8, 2, 0, 0, 0);

        // one scanline per row: filter byte + RGB pixels
        $rows = '';
        for ($y = 0; $y < $height; $y++) {
            $rows .= "\x00".str_repeat("\x20\x40\x80", $width);
        }

        return "\x89PNG\r\n\x1a\n"
            .self::chunk('IHDR', $ihdr)
            .self::chunk('IDAT', gzcompress($rows))
            .self::chunk('IEND', '');
    }

    private static function chunk(string $type, string $data): string
    {
        return pack('N', strlen($data)).$type.$data.pack('N', crc32($type.$data));
    }

    /**
     * A JPEG with a real APP1/EXIF segment carrying a GPS tag.
     * Used to prove that stored images never keep location metadata.
     */
    public static function jpegWithExif(string $name = 'geo.jpg', int $width = 200, int $height = 150): UploadedFile
    {
        $image = imagecreatetruecolor($width, $height);
        imagefill($image, 0, 0, imagecolorallocate($image, 120, 80, 200));
        ob_start();
        imagejpeg($image, null, 90);
        $jpeg = ob_get_clean();
        imagedestroy($image);

        // minimal APP1 Exif block: "Exif\0\0" + a GPS IFD pointer marker we can grep for
        $payload = "Exif\x00\x00" . "GPSLatitude=" . bin2hex('52.5200N');
        $segment = "\xFF\xE1" . pack('n', strlen($payload) + 2) . $payload;
        $withExif = substr($jpeg, 0, 2) . $segment . substr($jpeg, 2);

        $dir = storage_path('framework/testing');
        if (! is_dir($dir)) {
            mkdir($dir, 0777, true);
        }

        $path = $dir.'/'.uniqid('exif-', true).'.jpg';
        file_put_contents($path, $withExif);

        return new UploadedFile($path, $name, 'image/jpeg', null, true);
    }

    /** A PNG with a genuinely transparent region, for sticker tests. */
    public static function transparentPng(string $name = 'sticker.png', int $width = 64, int $height = 64): UploadedFile
    {
        $dir = storage_path('framework/testing');
        if (! is_dir($dir)) {
            mkdir($dir, 0777, true);
        }

        $image = imagecreatetruecolor($width, $height);
        imagesavealpha($image, true);
        imagefill($image, 0, 0, imagecolorallocatealpha($image, 0, 0, 0, 127));
        imagefilledellipse($image, (int) ($width / 2), (int) ($height / 2), (int) ($width / 2), (int) ($height / 2), imagecolorallocate($image, 220, 90, 140));

        $path = $dir.'/'.uniqid('sticker-', true).'.png';
        imagepng($image, $path);
        imagedestroy($image);

        return new UploadedFile($path, $name, 'image/png', null, true);
    }
}
