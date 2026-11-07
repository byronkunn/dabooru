<?php

namespace Database\Seeders;

use App\Models\Comic;
use App\Models\ComicChapter;
use App\Models\ComicPage;
use App\Models\Media;
use App\Models\Post;
use App\Models\PostMedia;
use App\Models\User;
use Illuminate\Database\Seeder;

/**
 * Builds mock comics out of the images already in sfw/.
 *
 * Each comic is a series with chapters, and each chapter is a set of pages taken
 * straight from sfw/image. Every chapter also gets exactly one feed post, the
 * same rule the real upload flow follows. Re-running is safe: existing series
 * are skipped.
 */
class SampleComicsSeeder extends Seeder
{
    public function run(): void
    {
        $manifest = json_decode((string) file_get_contents(database_path('data/sfw/manifest.json')), true);
        $images = $manifest['image'] ?? [];

        if ($images === []) {
            $this->command?->info('No images in database/data/sfw/manifest.json — nothing to build.');

            return;
        }

        $artist = User::where('username', 'avetetsuya-studios')->first()
            ?? User::whereNotNull('username')->where('is_unsorted', false)->first()
            ?? User::first();

        if (! $artist) {
            $this->command?->info('No user to own the sample comics.');

            return;
        }

        // three series, three chapters each, 4-6 pages per chapter
        $series = [
            [
                'slug' => 'kotatsu-days', 'title' => 'Kotatsu Days', 'rating' => 'sfw', 'status' => 'ongoing',
                'description' => 'A quiet slice-of-life about two roommates surviving winter at the kotatsu. Sample pages built from the sfw/ image set.',
                'chapter_titles' => ['A very slow morning', 'Snowed in', 'The heater breaks'],
            ],
            [
                'slug' => 'signal-noise', 'title' => 'Signal / Noise', 'rating' => 'suggestive', 'status' => 'ongoing',
                'description' => 'A late-night radio host and the regular caller she has never met. Sample pages built from the sfw/ image set.',
                'chapter_titles' => ['Dead air', 'The 3am caller', 'Static'],
            ],
            [
                'slug' => 'paper-trails', 'title' => 'Paper Trails', 'rating' => 'sfw', 'status' => 'completed',
                'description' => 'A short finished story about a courier who never misses a delivery. Sample pages built from the sfw/ image set.',
                'chapter_titles' => ['First drop', 'Lost address', 'Signed for'],
            ],
        ];

        $page = 0;
        $made = 0;

        foreach ($series as $spec) {
            if (Comic::where('slug', $spec['slug'])->exists()) {
                continue;
            }

            $comic = Comic::create([
                'user_id' => $artist->id,
                'slug' => $spec['slug'],
                'title' => $spec['title'],
                'description' => $spec['description'],
                'rating' => $spec['rating'],
                'status' => $spec['status'],
                'last_chapter_at' => now()->subHours(3),
            ]);

            // cover: the first image in the set
            $coverImage = $images[$page % count($images)];
            $cover = $this->media($artist, $coverImage);
            $comic->forceFill(['cover_media_id' => $cover->id])->save();

            // two published chapters then one already read to keep ordering realistic
            $publishedCount = count($spec['chapter_titles']);

            foreach ($spec['chapter_titles'] as $i => $chapterTitle) {
                $number = $i + 1;
                $pageCount = 4 + ($number % 3); // 4-6 pages

                $chapter = ComicChapter::create([
                    'comic_id' => $comic->id,
                    'number' => $number,
                    'title' => $chapterTitle,
                    'rating' => $spec['rating'],
                    'published_at' => now()->subDays(($publishedCount - $number) * 4),
                    'views' => random_int(120, 2600),
                ]);

                for ($p = 1; $p <= $pageCount; $p++) {
                    $image = $images[$page++ % count($images)];
                    ComicPage::create([
                        'comic_chapter_id' => $chapter->id,
                        'media_id' => $this->media($artist, $image)->id,
                        'position' => $p,
                    ]);
                }

                // one feed post per chapter, cover = first page
                $post = Post::create([
                    'user_id' => $artist->id,
                    'type' => 'comic',
                    'rating' => $spec['rating'],
                    'title' => $spec['title'].' · Ch. '.$number,
                    'caption' => $chapterTitle.'.',
                    'comic_chapter_id' => $chapter->id,
                    'created_at' => now()->subDays(($publishedCount - $number) * 4),
                    'updated_at' => now()->subDays(($publishedCount - $number) * 4),
                ]);

                $firstPage = $chapter->pages()->first();
                if ($firstPage) {
                    PostMedia::create(['post_id' => $post->id, 'media_id' => $firstPage->media_id, 'position' => 0]);
                }

                $made++;
            }

            $this->command?->info("  {$spec['title']} — ".count($spec['chapter_titles']).' chapters');
        }

        $this->command?->info("Sample comics ready: {$made} chapters from the sfw/ image set.");
    }

    private function media(User $artist, array $image): Media
    {
        return Media::create([
            'user_id' => $artist->id,
            'disk' => 'public',
            'path' => '/sfw/content/image/'.rawurlencode($image['file']),
            'kind' => 'image',
            'width' => $image['w'],
            'height' => $image['h'],
            'status' => 'ready',
            'mime_type' => 'image/jpeg',
        ]);
    }
}
