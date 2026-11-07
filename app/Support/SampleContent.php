<?php

namespace App\Support;

/**
 * Sample content that ships with the app: real images and videos kept under
 * database/data/sfw/ and served straight from public/sfw/content so it works
 * on any machine without seeding files into storage.
 *
 * database/data/sfw/manifest.json holds the dimensions of every file, so the
 * seeder never has to touch the filesystem or an image library.
 */
class SampleContent
{
    private static ?array $manifest = null;

    public static function manifest(): array
    {
        if (self::$manifest === null) {
            $path = database_path('data/sfw/manifest.json');
            self::$manifest = is_file($path)
                ? json_decode((string) file_get_contents($path), true) ?: []
                : [];
        }

        return self::$manifest;
    }

    public static function images(): array
    {
        return self::manifest()['image'] ?? [];
    }

    public static function videos(): array
    {
        // Zero-duration clips are broken encodes; skip them.
        return array_values(array_filter(
            self::manifest()['video'] ?? [],
            fn (array $v) => ($v['duration'] ?? 0) > 0
        ));
    }

    public static function avatars(): array
    {
        return self::manifest()['avatar'] ?? [];
    }

    public static function banners(): array
    {
        return self::manifest()['banner-image'] ?? [];
    }

    public static function url(string $folder, array $entry): string
    {
        return '/sfw/content/'.$folder.'/'.rawurlencode($entry['file']);
    }

    /** Deterministic pick so re-seeding produces the same gallery. */
    public static function pick(array $list, int $index): ?array
    {
        if ($list === []) {
            return null;
        }

        return $list[$index % count($list)];
    }
}
