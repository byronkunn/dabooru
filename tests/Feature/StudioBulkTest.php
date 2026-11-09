<?php

namespace Tests\Feature;

use App\Models\Comic;
use App\Models\ComicChapter;
use App\Models\Post;
use App\Models\Tag;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class StudioBulkTest extends TestCase
{
    use RefreshDatabase;

    private function artistWithWorks(int $count = 3): array
    {
        $artist = User::factory()->create(['username' => 'bulkartist', 'is_unsorted' => false]);
        $works = collect(range(1, $count))->map(fn ($i) => Post::create([
            'user_id' => $artist->id, 'type' => 'image', 'rating' => 'sfw', 'title' => "Work {$i}",
        ]))->all();

        return [$artist, $works];
    }

    public function test_bulk_add_tags(): void
    {
        [$artist, $works] = $this->artistWithWorks();

        $this->actingAs($artist)->post(route('studio.bulk'), [
            'works' => collect($works)->pluck('id')->all(),
            'action' => 'add_tags',
            'tags' => 'watercolor, sketch',
        ])->assertRedirect();

        foreach ($works as $work) {
            $this->assertEqualsCanonicalizing(['watercolor', 'sketch'], $work->fresh()->tags()->pluck('name')->all());
        }
    }

    public function test_bulk_remove_tags_only_touches_the_named_ones(): void
    {
        [$artist, $works] = $this->artistWithWorks(1);
        Tag::syncFor($works[0], ['watercolor', 'sketch']);

        $this->actingAs($artist)->post(route('studio.bulk'), [
            'works' => [$works[0]->id], 'action' => 'remove_tags', 'tags' => 'watercolor',
        ])->assertRedirect();

        $this->assertSame(['sketch'], $works[0]->fresh()->tags()->pluck('name')->all());
    }

    public function test_bulk_change_rating(): void
    {
        [$artist, $works] = $this->artistWithWorks(2);

        $this->actingAs($artist)->post(route('studio.bulk'), [
            'works' => collect($works)->pluck('id')->all(), 'action' => 'rating', 'rating' => 'explicit',
        ])->assertRedirect();

        foreach ($works as $work) {
            $this->assertSame('explicit', $work->fresh()->rating);
        }
    }

    public function test_preview_reports_the_effect_without_changing_anything(): void
    {
        [$artist, $works] = $this->artistWithWorks(2);
        Tag::syncFor($works[0], ['watercolor']);

        $this->actingAs($artist)->post(route('studio.bulk'), [
            'works' => collect($works)->pluck('id')->all(),
            'action' => 'add_tags',
            'tags' => 'watercolor',
            'preview' => true,
        ])->assertOk()->assertJson([
            'affected' => 1,
        ])->assertJsonCount(2, 'items');

        // nothing was written
        $this->assertNotContains('watercolor', $works[1]->fresh()->tags()->pluck('name')->all());
    }

    public function test_another_artists_works_cannot_be_bulk_edited(): void
    {
        [$artist] = $this->artistWithWorks(1);
        $intruder = User::factory()->create();
        $victimWork = Post::where('user_id', $artist->id)->first();

        $this->actingAs($intruder)->post(route('studio.bulk'), [
            'works' => [$victimWork->id], 'action' => 'rating', 'rating' => 'explicit',
        ])->assertStatus(422);

        $this->assertSame('sfw', $victimWork->fresh()->rating);
    }

    public function test_moving_works_into_a_series(): void
    {
        [$artist, $works] = $this->artistWithWorks(2);
        $comic = Comic::create(['user_id' => $artist->id, 'slug' => 'bulk-series', 'title' => 'Bulk Series', 'rating' => 'sfw', 'status' => 'ongoing']);
        $chapter = ComicChapter::create(['comic_id' => $comic->id, 'number' => 1, 'title' => 'One', 'rating' => 'sfw', 'published_at' => now()]);

        $this->actingAs($artist)->post(route('studio.bulk'), [
            'works' => collect($works)->pluck('id')->all(),
            'action' => 'series',
            'comic_id' => $comic->id,
            'chapter_id' => $chapter->id,
        ])->assertRedirect();

        foreach ($works as $work) {
            $this->assertSame($chapter->id, $work->fresh()->comic_chapter_id);
        }
    }

    public function test_a_chapter_from_another_artists_series_is_rejected(): void
    {
        [$artist, $works] = $this->artistWithWorks(1);
        $other = User::factory()->create();
        $comic = Comic::create(['user_id' => $other->id, 'slug' => 'theirs', 'title' => 'Theirs', 'rating' => 'sfw', 'status' => 'ongoing']);
        $chapter = ComicChapter::create(['comic_id' => $comic->id, 'number' => 1, 'title' => 'One', 'rating' => 'sfw']);

        $this->actingAs($artist)->post(route('studio.bulk'), [
            'works' => [$works[0]->id], 'action' => 'series', 'comic_id' => $comic->id, 'chapter_id' => $chapter->id,
        ])->assertStatus(422);
    }

    public function test_guests_cannot_use_bulk_edit(): void
    {
        [$artist, $works] = $this->artistWithWorks(1);

        $this->post(route('studio.bulk'), ['works' => [$works[0]->id], 'action' => 'rating', 'rating' => 'explicit'])
            ->assertRedirect(route('login'));
    }
}
