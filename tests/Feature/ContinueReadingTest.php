<?php

namespace Tests\Feature;

use App\Models\Comic;
use App\Models\ComicChapter;
use App\Models\ComicPage;
use App\Models\Media;
use App\Models\ReadingProgress;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ContinueReadingTest extends TestCase
{
    use RefreshDatabase;

    private function series(User $artist, int $pages = 4): array
    {
        $comic = Comic::create([
            'user_id' => $artist->id, 'slug' => 'test-series', 'title' => 'Test Series',
            'rating' => 'sfw', 'status' => 'ongoing',
        ]);
        $chapter = ComicChapter::create([
            'comic_id' => $comic->id, 'number' => 1, 'title' => 'One',
            'rating' => 'sfw', 'published_at' => now(),
        ]);
        for ($i = 1; $i <= $pages; $i++) {
            $media = Media::create([
                'user_id' => $artist->id, 'disk' => 'public', 'path' => "p{$i}.png",
                'kind' => 'image', 'status' => 'ready', 'mime_type' => 'image/png',
            ]);
            ComicPage::create(['comic_chapter_id' => $chapter->id, 'media_id' => $media->id, 'position' => $i]);
        }

        return [$comic, $chapter];
    }

    public function test_a_chapter_read_to_the_last_page_drops_out_of_continue_reading(): void
    {
        $artist = User::factory()->create(['is_unsorted' => false]);
        [$comic, $chapter] = $this->series($artist, 4);
        $reader = User::factory()->create();

        // partway through: still continuing
        ReadingProgress::create([
            'user_id' => $reader->id, 'comic_id' => $comic->id,
            'comic_chapter_id' => $chapter->id, 'page' => 2,
        ]);
        $this->actingAs($reader)->get(route('comics.index'))
            ->assertInertia(fn ($page) => $page->has('continueReading', 1));

        // read to the end: no longer offered
        ReadingProgress::where('user_id', $reader->id)->update(['page' => 4]);
        $this->actingAs($reader)->get(route('comics.index'))
            ->assertInertia(fn ($page) => $page->has('continueReading', 0));
    }

    public function test_a_page_past_the_end_still_counts_as_finished(): void
    {
        $artist = User::factory()->create(['is_unsorted' => false]);
        [$comic, $chapter] = $this->series($artist, 3);
        $reader = User::factory()->create();

        ReadingProgress::create([
            'user_id' => $reader->id, 'comic_id' => $comic->id,
            'comic_chapter_id' => $chapter->id, 'page' => 9,
        ]);

        $this->actingAs($reader)->get(route('comics.index'))
            ->assertInertia(fn ($page) => $page->has('continueReading', 0));
    }
}
