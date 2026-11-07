<?php

namespace Tests\Feature;

use App\Models\Comic;
use App\Models\Post;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class SearchTest extends TestCase
{
    use RefreshDatabase;

    private function library(): array
    {
        $rin = User::factory()->create(['username' => 'rin_senpai', 'name' => 'Rin Senpai']);
        $mira = User::factory()->create(['username' => 'mira_ink', 'name' => 'mira.ink']);

        $quiet = Post::create(['user_id' => $rin->id, 'type' => 'image', 'rating' => 'sfw', 'title' => 'Mountain quiet', 'caption' => 'A lake study.', 'likes_count' => 50]);
        $adult = Post::create(['user_id' => $mira->id, 'type' => 'image', 'rating' => 'explicit', 'title' => 'Mountain at night', 'caption' => 'Explicit alt.', 'likes_count' => 10]);
        $other = Post::create(['user_id' => $mira->id, 'type' => 'image', 'rating' => 'sfw', 'title' => 'Tea house', 'caption' => 'Ink and wash.', 'likes_count' => 5]);

        Comic::create(['user_id' => $rin->id, 'slug' => 'neon-after-dark', 'title' => 'Neon After Dark', 'rating' => 'explicit', 'status' => 'ongoing']);

        return compact('rin', 'mira', 'quiet', 'adult', 'other');
    }

    public function test_search_page_renders_without_a_term(): void
    {
        $this->get('/search')
            ->assertOk()
            ->assertInertia(fn ($page) => $page
                ->component('Search/Index')
                ->where('term', '')
                ->where('searchable', false));
    }

    public function test_finds_works_by_title(): void
    {
        ['quiet' => $quiet, 'adult' => $adult] = $this->library();

        $this->get('/search?q=mountain')
            ->assertOk()
            ->assertInertia(fn ($page) => $page
                ->where('searchable', true)
                ->where('counts.works', 1)          // explicit hidden for guests
                ->where('works.0.id', $quiet->id));
    }

    public function test_finds_works_by_caption(): void
    {
        ['other' => $other] = $this->library();

        $this->get('/search?q=ink and wash')
            ->assertOk()
            ->assertInertia(fn ($page) => $page
                ->where('counts.works', 1)
                ->where('works.0.id', $other->id));
    }

    public function test_finds_works_by_artist_handle(): void
    {
        $this->library();

        $this->get('/search?q=rin_senpai')
            ->assertOk()
            ->assertInertia(fn ($page) => $page->where('counts.works', 1));
    }

    public function test_explicit_works_are_hidden_from_guests(): void
    {
        ['adult' => $adult] = $this->library();

        $this->get('/search?q=mountain')
            ->assertOk()
            ->assertInertia(fn ($page) => $page->where('counts.works', 1));

        $this->actingAs(User::factory()->create(['show_explicit' => true]))
            ->get('/search?q=mountain')
            ->assertOk()
            ->assertInertia(fn ($page) => $page->where('counts.works', 2));
    }

    public function test_finds_comics_and_people(): void
    {
        $this->library();

        // the seed comic is explicit, so a guest must not see it
        $this->get('/search?q=neon')
            ->assertOk()
            ->assertInertia(fn ($page) => $page
                ->where('tab', 'works')
                ->where('counts.comics', 0));

        $this->actingAs(User::factory()->create(['show_explicit' => true]))
            ->get('/search?q=neon')
            ->assertOk()
            ->assertInertia(fn ($page) => $page->where('counts.comics', 1));

        $this->get('/search?q=mira&tab=people')
            ->assertOk()
            ->assertInertia(fn ($page) => $page
                ->where('tab', 'people')
                ->where('people.0.username', 'mira_ink'));
    }

    public function test_short_terms_are_not_searched(): void
    {
        $this->library();

        $this->get('/search?q=a')
            ->assertOk()
            ->assertInertia(fn ($page) => $page
                ->where('searchable', false)
                ->where('counts.works', 0));
    }

    public function test_like_wildcards_in_the_term_are_escaped(): void
    {
        $this->library();

        // a bare % must not act as a wildcard
        $this->get('/search?q=%25')
            ->assertOk()
            ->assertInertia(fn ($page) => $page->where('counts.works', 0));
    }

    public function test_result_tab_falls_back_to_works_for_unknown_values(): void
    {
        $this->get('/search?q=art&tab=banana')
            ->assertOk()
            ->assertInertia(fn ($page) => $page->where('tab', 'works'));
    }

    public function test_suggestions_endpoint_returns_people_works_and_comics(): void
    {
        $this->library();

        $this->getJson('/search/suggest?q=mira')
            ->assertOk()
            ->assertJsonStructure(['people', 'works', 'comics', 'more'])
            ->assertJsonPath('people.0.hint', '@mira_ink');

        $this->actingAs(User::factory()->create(['show_explicit' => true]))
            ->getJson('/search/suggest?q=neon')
            ->assertOk()
            ->assertJsonPath('comics.0.label', 'Neon After Dark');
    }

    public function test_suggestions_are_empty_for_short_terms(): void
    {
        $this->getJson('/search/suggest?q=x')
            ->assertOk()
            ->assertJson(['people' => [], 'works' => [], 'comics' => []]);
    }

    public function test_gallery_accepts_a_q_parameter(): void
    {
        ['other' => $other] = $this->library();

        $this->get('/?q=tea')
            ->assertOk()
            ->assertInertia(fn ($page) => $page
                ->where('filters.q', 'tea')
                ->has('posts', 1)
                ->where('posts.0.id', $other->id));
    }
}
