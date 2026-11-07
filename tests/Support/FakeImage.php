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
}
