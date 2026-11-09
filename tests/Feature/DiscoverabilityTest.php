<?php

namespace Tests\Feature;

use App\Models\Post;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class DiscoverabilityTest extends TestCase
{
    use RefreshDatabase;

    public function test_the_sitemap_lists_public_pages_and_excludes_explicit_work(): void
    {
        $artist = User::factory()->create(['is_unsorted' => false]);
        $sfw = Post::create(['user_id' => $artist->id, 'type' => 'image', 'rating' => 'sfw', 'title' => 'Safe']);
        $explicit = Post::create(['user_id' => $artist->id, 'type' => 'image', 'rating' => 'explicit', 'title' => 'Adult']);

        $response = $this->get(route('sitemap'))->assertOk();
        $xml = $response->getContent();

        $this->assertStringContainsString(route('posts.show', $sfw), $xml);
        $this->assertStringNotContainsString(route('posts.show', $explicit), $xml, 'explicit work must not be in a sitemap');
        $this->assertStringContainsString(route('gallery'), $xml);
    }

    public function test_the_sitemap_is_reachable_without_passing_the_age_gate(): void
    {
        config(['app.age_gate_test_bypass' => false]);

        $this->withoutMiddleware(\App\Http\Middleware\EnsureAgeConfirmed::class);
        $this->get(route('sitemap'))->assertOk();
    }

    public function test_a_work_page_carries_share_metadata(): void
    {
        $artist = User::factory()->create(['is_unsorted' => false]);
        $post = Post::create(['user_id' => $artist->id, 'type' => 'image', 'rating' => 'sfw', 'title' => 'Share me']);

        $html = $this->get(route('posts.show', $post))->assertOk()->getContent();
        $this->assertStringContainsString('og:title', $html);
        $this->assertStringContainsString('Share me', $html);
    }

    public function test_explicit_work_never_exposes_a_preview_image(): void
    {
        $artist = User::factory()->create(['is_unsorted' => false]);
        $post = Post::create(['user_id' => $artist->id, 'type' => 'image', 'rating' => 'explicit', 'title' => 'Not in previews']);

        $html = $this->get(route('posts.show', $post))->assertOk()->getContent();
        $this->assertStringNotContainsString('og:image', $html, 'explicit work must not leak a thumbnail to link previews');
    }
}
