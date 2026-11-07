<?php

namespace App\Support;

use Illuminate\Support\Str;

/**
 * Sample content that ships with the app: the real files in sfw/.
 *
 * Nothing here invents artists, titles or captions. Every post is a file,
 * every artist name comes from the file name where the file carries one, and
 * posts with no nameable author stay unattributed rather than being credited
 * to a made-up persona.
 *
 * database/data/sfw/manifest.json holds the dimensions of every file so the
 * seeder never needs an image library or a filesystem walk.
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

    /** Videos with a real duration; broken encodes are skipped. */
    public static function videos(): array
    {
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

    public static function videoPoster(array $video): ?string
    {
        $poster = preg_replace('/\.mp4$/i', '.jpg', $video['file']);

        return is_file(base_path('sfw/thumbs/'.$poster))
            ? '/sfw/content/thumbs/'.rawurlencode($poster)
            : null;
    }

    /* ---------------------------------------------------------------- naming */

    /**
     * Files whose name already says who made them. Only these get a public
     * credit; everything else is a bare hash and stays unattributed instead of
     * being assigned an invented persona.
     *
     *   john-staub-marvel-loki-western-color-jstaub.jpg -> John Staub
     *   pavel-yankovich-01.jpg                          -> Pavel Yankovich
     *
     * Untitled / hashed / "sample_<hash>" files return null.
     */
    public static function authorFromFilename(string $file): ?array
    {
        $stem = pathinfo($file, PATHINFO_FILENAME);

        $tokens = self::meaningfulTokens($stem);

        if (count($tokens) < 2) {
            return null;
        }

        // The leading two cleaned tokens are the person's name; anything after
        // that is subject matter or a credit tag.
        $name = implode(' ', array_map(fn ($w) => mb_convert_case($w, MB_CASE_TITLE), array_slice($tokens, 0, 2)));

        return [
            'name' => $name,
            'username' => Str::slug($name),
        ];
    }

    /** A human title derived from the file name, or null when it has none. */
    public static function titleFromFilename(string $file): ?string
    {
        $tokens = self::meaningfulTokens(pathinfo($file, PATHINFO_FILENAME));

        if ($tokens === []) {
            return null;
        }

        return mb_convert_case(implode(' ', $tokens), MB_CASE_TITLE);
    }

    /**
     * Split a file name into words worth showing: no numbers, no hashes,
     * no generic project words.
     */
    private static function meaningfulTokens(string $stem): array
    {
        // "sample_<hash>" and pure hashes carry no meaning at all
        if (preg_match('/^[a-f0-9]{16,}$/i', $stem) || preg_match('/^sample[_ -][a-f0-9]{8,}/i', $stem)) {
            return [];
        }

        $stop = [
            'sample', 's', 'v', 'n', 'orig', 'preview', 'final', 'work', 'photo',
            'img', 'image', 'dsc', 'file',
        ];

        $words = preg_split('/[-_ .]+/', $stem) ?: [];

        $words = array_filter($words, function ($w) use ($stop) {
            $w = (string) $w;

            return $w !== ''
                && ! ctype_digit($w)
                && ! preg_match('/^[a-f0-9]{6,}$/i', $w)
                && ! in_array(strtolower($w), $stop, true)
                && ! preg_match('/^\d{3,}$/', $w);
        });

        return array_values($words);
    }
}
