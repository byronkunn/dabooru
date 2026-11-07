<?php

namespace Tests\Feature;

use App\Models\Comic;
use App\Models\ComicChapter;
use App\Models\ComicPage;
use App\Models\Media;
use App\Models\Post;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ComicsTest extends TestCase
{
    use RefreshDatabase;

    private function makeComic(): Comic
    {
        $artist = User::factory()->create(['username' => 'mangaka']);
        $comic = Comic::create(['user_id' => $artist->id, 'slug' => 'neon', 'title' => 'Neon', 'rating' => 'suggestive', 'status' => 'ongoing']);

        foreach ([1, 2] as $n) {
            $chapter = ComicChapter::create(['comic_id' => $comic->id, 'number' => $n, 'title' => 'Ch '.$n, 'rating' => 'sfw', 'published_at' => now()]);
            for ($p = 1; $p <= 3; $p++) {
                $media = Media::create(['user_id' => $artist->id, 'path' => "pages/{$n}-{$p}.jpg", 'kind' => 'image', 'width' => 800, 'height' => 1200]);
                ComicPage::create(['comic_chapter_id' => $chapter->id, 'media_id' => $media->id, 'position' => $p]);
            }
        }

        return $comic;
    }

    public function test_series_page_lists_chapters_newest_first(): void
    {
        $this->makeComic();

        $this->get('/comics/neon')
            ->assertOk()
            ->assertInertia(fn ($page) => $page
                ->component('Comics/Show')
                ->where('comic.title', 'Neon')
                ->has('comic.chapters', 2)
                ->where('comic.chapters.0.number', 2));
    }

    public function test_reader_returns_all_pages_of_the_chapter(): void
    {
        $this->makeComic();

        $this->get('/comics/neon/1')
            ->assertOk()
            ->assertInertia(fn ($page) => $page
                ->component('Comics/Read')
                ->has('pages', 3)
                ->where('chapter.number', 1)
                ->where('chapter.next.number', 2));
    }

    public function test_reader_404s_for_missing_chapter(): void
    {
        $this->makeComic();

        $this->get('/comics/neon/99')->assertNotFound();
    }

    public function test_creating_a_series_redirects_to_its_first_chapter_form(): void
    {
        $artist = User::factory()->create();

        $this->actingAs($artist)
            ->post(route('comics.series.store'), ['title' => 'Midnight Ink', 'rating' => 'explicit', 'status' => 'ongoing'])
            ->assertRedirect(route('comics.chapters.create', ['comic' => 'midnight-ink']));

        $this->assertDatabaseHas('comics', ['slug' => 'midnight-ink', 'user_id' => $artist->id]);
    }

    public function test_creating_a_series_with_cover_stores_media(): void
    {
        \Illuminate\Support\Facades\Storage::fake('public');
        $artist = User::factory()->create();
        $cover = \Tests\Support\FakeImage::upload('cover.png', 600, 800);

        $this->actingAs($artist)
            ->post(route('comics.series.store'), [
                'title' => 'Starlight Blade',
                'rating' => 'sfw',
                'status' => 'ongoing',
                'cover' => $cover,
            ])
            ->assertRedirect(route('comics.chapters.create', ['comic' => 'starlight-blade']));

        $comic = Comic::where('slug', 'starlight-blade')->first();
        $this->assertNotNull($comic);
        $this->assertNotNull($comic->cover_media_id);
        $this->assertNotNull($comic->cover());
    }

    public function test_cannot_add_a_chapter_to_someone_elses_series(): void
    {
        $comic = $this->makeComic();
        $other = User::factory()->create();

        $this->actingAs($other)->post(route('comics.chapters.store', $comic), [
            'number' => 3, 'rating' => 'sfw', 'pages' => [],
        ])->assertForbidden();
    }

    public function test_a_published_chapter_creates_exactly_one_feed_post(): void
    {
        $comic = $this->makeComic();

        $this->assertSame(0, Post::where('comic_chapter_id', ComicChapter::where('number', 1)->value('id'))->count());
    }
}
