<?php

namespace Tests\Feature;

use App\Models\Comic;
use App\Models\ComicChapter;
use App\Models\ComicPage;
use App\Models\Conversation;
use App\Models\Message;
use App\Models\Post;
use Database\Seeders\DatabaseSeeder;
use Database\Seeders\SampleComicsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class SampleContentTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(DatabaseSeeder::class);
    }

    public function test_sample_comics_are_built_entirely_from_sfw_images(): void
    {
        $this->assertSame(3, Comic::whereIn('slug', ['kotatsu-days', 'signal-noise', 'paper-trails'])->count());

        foreach (['kotatsu-days', 'signal-noise', 'paper-trails'] as $slug) {
            $comic = Comic::where('slug', $slug)->firstOrFail();
            $this->assertSame(3, $comic->chapters()->count(), "{$slug} has 3 chapters");
            $this->assertNotNull($comic->cover(), "{$slug} has a cover");

            // every page is a real file in sfw/
            foreach ($comic->chapters as $chapter) {
                $this->assertGreaterThanOrEqual(4, $chapter->pages()->count());
                foreach ($chapter->pages as $page) {
                    $url = $page->media->url();
                    $this->assertStringStartsWith('/sfw/content/image/', $url);
                    $this->assertFileExists(base_path('sfw/image/'.rawurldecode(basename($url))));
                }
            }

            // one feed post per published chapter
            $this->assertSame(3, Post::where('type', 'comic')->whereIn('comic_chapter_id', $comic->chapters->pluck('id'))->count());
        }
    }

    public function test_sample_comic_series_page_and_reader_render(): void
    {
        $this->get('/comics/kotatsu-days')->assertOk()
            ->assertInertia(fn ($page) => $page->where('comic.title', 'Kotatsu Days')->has('comic.chapters', 3));

        $this->get('/comics/kotatsu-days/1')->assertOk()
            ->assertInertia(fn ($page) => $page->has('pages'));
    }

    public function test_database_seeder_does_not_create_mock_messages(): void
    {
        $this->assertSame(0, Conversation::count());
        $this->assertSame(0, Message::count());
    }

    public function test_reseeding_sample_comics_does_not_duplicate_series(): void
    {
        $before = Comic::count();
        $this->seed(SampleComicsSeeder::class);
        $this->assertSame($before, Comic::count());
    }
}
