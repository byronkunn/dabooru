<?php

namespace Tests\Feature;

use App\Models\Comic;
use App\Models\Post;
use App\Models\Tag;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class SearchTest extends TestCase
{
    use RefreshDatabase;

    private function library(): array
    {
        $artist = User::factory()->create(['username' => 'rin_senpai', 'name' => 'Rin Senpai']);
        $quiet = Post::create(['user_id' => $artist->id, 'type' => 'image', 'rating' => 'sfw', 'title' => 'Mountain quiet', 'caption' => 'A lake study.']);
        $adult = Post::create(['user_id' => $artist->id, 'type' => 'image', 'rating' => 'explicit', 'title' => 'Mountain at night']);
        $other = Post::create(['user_id' => $artist->id, 'type' => 'image', 'rating' => 'sfw', 'title' => 'Tea house', 'caption' => 'Ink and wash.', 'artist_name' => 'Outside Artist']);
        Tag::syncFor($quiet, ['line art', 'landscape']);
        Tag::syncFor($other, ['line art']);

        return compact('artist', 'quiet', 'adult', 'other');
    }

    public function test_the_old_search_page_is_gone_and_browse_is_the_results_page(): void
    {
        // /search used to redirect to the gallery; it was a vestigial route, so it
        // now 404s. The operator syntax it accepted lives on in the gallery.
        $this->get('/search')->assertNotFound();

        $this->get(route('gallery', ['q' => 'mountain by:"Rin" type:image', 'window' => 'all']))->assertOk();
    }

    public function test_browse_searches_title_caption_handle_and_external_credit(): void
    {
        ['quiet' => $quiet, 'other' => $other] = $this->library();
        foreach (['mountain' => $quiet, 'lake study' => $quiet, 'ink and wash' => $other, 'Outside' => $other] as $term => $post) {
            $this->get(route('gallery', ['q' => $term, 'window' => 'all']))->assertOk()->assertInertia(fn ($page) => $page
                ->component('Gallery/Index')->has('posts', 1)->where('posts.0.id', $post->id)->missing('stats'));
        }
        $this->get('/?q=rin_senpai&window=all')->assertInertia(fn ($page) => $page->has('posts', 2));
    }

    public function test_multiple_tags_and_text_are_combined_and_existing_filters_preserved(): void
    {
        ['quiet' => $quiet] = $this->library();
        $query = 'mountain tag:"line art" #landscape by:rin_senpai type:image -tag:watercolor';
        $this->get(route('gallery', ['q' => $query, 'window' => 'all', 'sort' => 'newest']))->assertInertia(fn ($page) => $page
            ->has('posts', 1)->where('posts.0.id', $quiet->id)->where('filters.q', $query)
            ->where('searchTags.included', ['line art', 'landscape'])->where('searchTags.excluded', ['watercolor'])
            ->where('filters.sort', 'newest')->where('filters.window', 'all')->where('filters.format', 'image'));
        $this->get(route('gallery', ['q' => '#landscape -tag:"line art"']))->assertInertia(fn ($page) => $page->has('posts', 0));
    }

    public function test_explicit_preference_and_query_override_still_apply(): void
    {
        $this->library();
        $this->get('/?q=mountain')->assertInertia(fn ($page) => $page->has('posts', 1));
        $this->actingAs(User::factory()->create(['show_explicit' => true]))
            ->get('/?q=mountain')->assertInertia(fn ($page) => $page->has('posts', 2));
        $this->get('/?q=mountain&explicit=0')->assertInertia(fn ($page) => $page->has('posts', 1));
    }

    public function test_quoted_work_title_and_literal_percent_are_searchable(): void
    {
        ['quiet' => $quiet] = $this->library();
        $this->get(route('gallery', ['q' => '"Mountain quiet"']))->assertInertia(fn ($page) => $page
            ->has('posts', 1)->where('posts.0.id', $quiet->id));
        $this->get('/?q=%25')->assertInertia(fn ($page) => $page->has('posts', 0));
    }

    public function test_autocomplete_returns_tags_artists_works_and_visible_comics(): void
    {
        ['artist' => $artist] = $this->library();
        Comic::create(['user_id' => $artist->id, 'slug' => 'mountain', 'title' => 'Mountain', 'rating' => 'explicit', 'status' => 'ongoing']);
        $this->getJson('/search/suggest?q=rin')->assertOk()->assertJsonPath('people.0.hint', '@rin_senpai');
        $this->getJson('/search/suggest?q=line')->assertOk()->assertJsonPath('tags.0.tag', 'line art');
        $this->getJson('/search/suggest?q=mountain')->assertOk()->assertJsonCount(0, 'comics')->assertJsonCount(1, 'works');
        $this->actingAs(User::factory()->create(['show_explicit' => true]))
            ->getJson('/search/suggest?q=mountain')->assertJsonPath('comics.0.label', 'Mountain');
        $this->getJson('/search/suggest?q=x')->assertOk()->assertJson(['people' => [], 'works' => [], 'comics' => []]);
        $this->getJson(route('search.trending'))->assertOk()->assertJsonStructure(['tags']);
    }
}
