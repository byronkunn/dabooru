<?php

namespace Tests\Feature;

use App\Models\Comic;
use App\Models\ComicChapter;
use App\Models\Post;
use App\Models\Tag;
use App\Models\User;
use App\Models\WikiPage;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class SectionSearchTest extends TestCase
{
    use RefreshDatabase;

    public function test_comics_search_and_suggestions_only_return_matching_comics(): void
    {
        $artist = User::factory()->create(['username' => 'inkcreator']);
        $comic = Comic::create(['user_id' => $artist->id, 'slug' => 'neon', 'title' => 'Neon', 'description' => 'Night city', 'rating' => 'sfw']);
        ComicChapter::create(['comic_id' => $comic->id, 'number' => 1, 'title' => 'Homecoming', 'rating' => 'sfw', 'published_at' => now()]);
        ComicChapter::create(['comic_id' => $comic->id, 'number' => 2, 'title' => 'Secret draft', 'rating' => 'sfw']);
        Comic::create(['user_id' => $artist->id, 'slug' => 'other', 'title' => 'Another series', 'rating' => 'sfw']);
        Comic::create(['user_id' => $artist->id, 'slug' => 'explicit', 'title' => 'Neon private', 'rating' => 'explicit']);
        Post::create(['user_id' => $artist->id, 'type' => 'image', 'title' => 'Neon illustration', 'rating' => 'sfw']);
        Tag::create(['name' => 'neon']);
        WikiPage::create(['title' => 'Neon guide', 'body' => 'A guide']);

        foreach (['Neon', 'Night city', 'Homecoming'] as $term) {
            $this->get(route('comics.index', ['q' => $term]))->assertOk()->assertInertia(fn ($page) => $page
                ->component('Comics/Index')->where('searchQuery', $term)->has('comics', 1)->where('comics.0.id', $comic->id));
            $this->getJson(route('search.suggest', ['q' => $term, 'scope' => 'comics']))->assertOk()
                ->assertJsonCount(1, 'comics')->assertJsonPath('comics.0.label', 'Neon')
                ->assertJsonMissingPath('works')->assertJsonMissingPath('tags')->assertJsonMissingPath('people');
        }
        $this->get(route('comics.index', ['q' => 'inkcreator']))->assertInertia(fn ($page) => $page->has('comics', 2));
        foreach (['Secret draft', 'missing comic'] as $term) {
            $this->get(route('comics.index', ['q' => $term]))->assertInertia(fn ($page) => $page->has('comics', 0));
        }
        $this->actingAs(User::factory()->create(['show_explicit' => true]))
            ->get(route('comics.index', ['q' => 'Neon']))->assertInertia(fn ($page) => $page->has('comics', 2));
        $this->get(route('comics.index'))->assertInertia(fn ($page) => $page->has('comics', 3)->where('searchQuery', ''));
    }

    public function test_knowledge_search_filters_tags_and_wiki_without_gallery_results(): void
    {
        $tag = Tag::create(['name' => 'watercolor', 'category' => 'medium']);
        DB::table('tag_aliases')->insert(['tag_id' => $tag->id, 'name' => 'aqua painting']);
        Tag::create(['name' => 'other']);
        Tag::create(['name' => 'aqua restricted', 'wiki_rating' => 'explicit']);
        $guide = WikiPage::create(['title' => 'Painting guide', 'body' => 'Use aqua painting techniques.']);
        WikiPage::create(['title' => 'Unrelated guide', 'body' => 'Drawing']);
        WikiPage::create(['title' => 'Aqua restricted guide', 'body' => 'Aqua', 'rating' => 'explicit']);
        Post::create(['type' => 'image', 'title' => 'Aqua artwork', 'rating' => 'sfw']);
        User::factory()->create(['name' => 'Aqua Artist']);

        $this->get(route('knowledge.index', ['q' => 'aqua', 'category' => 'medium', 'needs_wiki' => 1]))
            ->assertOk()->assertInertia(fn ($page) => $page->component('Knowledge/Index')
            ->has('tags.data', 1)->where('tags.data.0.id', $tag->id)
            ->has('articles.data', 1)->where('articles.data.0.id', $guide->id));
        $this->get(route('knowledge.index', ['q' => 'Painting guide']))
            ->assertInertia(fn ($page) => $page->has('tags.data', 0)->has('articles.data', 1));
        $this->getJson(route('search.suggest', ['q' => 'aqua', 'scope' => 'knowledge']))->assertOk()
            ->assertJsonCount(1, 'tags')->assertJsonPath('tags.0.tag', 'watercolor')
            ->assertJsonCount(1, 'articles')->assertJsonPath('articles.0.label', 'Painting guide')
            ->assertJsonMissingPath('works')->assertJsonMissingPath('people')->assertJsonMissingPath('comics');
        $this->getJson(route('search.suggest', ['q' => '', 'scope' => 'knowledge']))
            ->assertExactJson(['tags' => [], 'articles' => []]);
        $this->get(route('knowledge.index', ['q' => 'not found']))
            ->assertInertia(fn ($page) => $page->has('tags.data', 0)->has('articles.data', 0));
        $this->actingAs(User::factory()->create(['show_explicit' => true]))
            ->getJson(route('search.suggest', ['q' => 'aqua', 'scope' => 'knowledge']))
            ->assertJsonCount(2, 'tags')->assertJsonCount(2, 'articles');
        $this->get(route('knowledge.index'))->assertInertia(fn ($page) => $page->has('tags.data', 3)->has('articles.data', 3));
    }

    public function test_unknown_autocomplete_scope_is_rejected(): void
    {
        $this->getJson(route('search.suggest', ['q' => 'neon', 'scope' => 'invalid']))->assertUnprocessable()->assertJsonValidationErrors('scope');
    }
}
