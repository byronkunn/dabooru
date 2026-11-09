<?php

namespace Tests\Feature;

use App\Models\Block;
use App\Models\Post;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ArtistPagesTest extends TestCase
{
    use RefreshDatabase;

    private function work(User $uploader, string $artist, array $attributes = []): Post
    {
        return Post::create($attributes + [
            'user_id' => $uploader->id, 'artist_name' => $artist,
            'type' => 'image', 'rating' => 'sfw', 'is_original' => false, 'title' => 'Shared work',
        ]);
    }

    public function test_artist_page_groups_exact_credits_across_uploaders_without_creating_an_account(): void
    {
        [$first, $second] = User::factory()->count(2)->create();
        $one = $this->work($first, 'Outside Artist');
        $two = $this->work($second, 'Outside Artist');
        $this->work($first, 'Outside Artist Studio');
        $this->work($first, 'Other credit');
        $url = route('artists.show', ['name' => 'Outside Artist'], false);

        $this->get($url)->assertOk()->assertInertia(fn ($page) => $page
            ->component('Artists/Show')
            ->where('artist.name', 'Outside Artist')->where('artist.works', 2)
            ->has('posts', 2)->where('posts.0.id', $two->id)->where('posts.1.id', $one->id)
            ->where('posts.0.external_artist.page_url', $url)
            ->missing('profile')->missing('relationship'));
        $this->assertDatabaseCount('users', 2);
        $this->get(route('artists.index', ['q' => 'Outside']))->assertInertia(fn ($page) => $page
            ->component('Artists/Index')->has('artists', 2)
            ->where('artists.0.name', 'Outside Artist')->where('artists.0.works', 2));
        $this->get(route('artists.show', ['name' => 'Unknown']))->assertNotFound();
    }

    public function test_artist_pages_directory_and_search_respect_hidden_ratings_drafts_deletion_and_blocks(): void
    {
        [$viewer, $uploader, $blocked] = User::factory()->count(3)->create();
        $visible = $this->work($uploader, 'Safe credit');
        $explicit = $this->work($uploader, 'Adult credit', ['rating' => 'explicit']);
        $this->work($uploader, 'Draft credit', ['is_draft' => true]);
        $deleted = $this->work($uploader, 'Deleted credit');
        $deleted->delete();
        $this->work($blocked, 'Blocked credit');
        Block::create(['blocker_id' => $viewer->id, 'blocked_id' => $blocked->id]);
        $this->actingAs($viewer);
        $this->get(route('artists.index'))->assertInertia(fn ($page) => $page
            ->has('artists', 1)->where('artists.0.name', 'Safe credit'));
        foreach (['Adult credit', 'Draft credit', 'Deleted credit', 'Blocked credit'] as $name) {
            $this->get(route('artists.show', ['name' => $name]))->assertNotFound();
        }
        $this->get(route('artists.show', ['name' => 'Safe credit']))->assertInertia(fn ($page) => $page
            ->has('posts', 1)->where('posts.0.id', $visible->id));
        $viewer->update(['show_explicit' => true]);
        $this->get(route('artists.show', ['name' => 'Adult credit']))->assertInertia(fn ($page) => $page
            ->has('posts', 1)->where('posts.0.id', $explicit->id));
    }

    public function test_search_and_related_works_link_to_artist_not_uploader_and_existing_credit_claims_remain_separate(): void
    {
        [$uploader, $other, $artist] = User::factory()->count(3)->create();
        $post = $this->work($uploader, 'Outside Artist');
        $related = $this->work($other, 'Outside Artist');
        $this->work($uploader, 'Different artist');
        $this->getJson(route('search.suggest', ['q' => 'Outside']))->assertOk()
            ->assertJsonPath('external_artists.0.url', route('artists.show', ['name' => 'Outside Artist'], false));
        $this->get(route('gallery', ['q' => 'Outside', 'window' => 'all']))->assertInertia(fn ($page) => $page
            ->has('posts', 2)->where('posts.0.artist_name', 'Outside Artist'));
        $this->get(route('posts.show', $post))->assertInertia(fn ($page) => $page
            ->has('more', 1)->where('more.0.id', $related->id)->where('post.user.id', $uploader->id));
        $post->forceFill(['credited_artist_id' => $artist->id])->save();
        $this->get(route('artists.show', ['name' => 'Outside Artist']))->assertInertia(fn ($page) => $page
            ->where('posts.1.credited_artist.username', $artist->username)
            ->where('posts.1.user.id', $uploader->id));
        $this->get(route('profile.show', ['username' => $artist->username, 'tab' => 'credited']))->assertOk();
    }

    public function test_artist_work_pagination_and_special_names_are_preserved(): void
    {
        $uploader = User::factory()->create();
        $name = 'Art / Ink & "Light"';
        for ($i = 0; $i < 25; $i++) {
            $this->work($uploader, $name);
        }
        $this->get(route('artists.show', ['name' => $name]))->assertInertia(fn ($page) => $page
            ->where('artist.name', $name)->where('artist.works', 25)->has('posts', 24)
            ->where('nextPage', fn ($url) => str_contains($url, 'page=2') && str_contains($url, 'name=')));
        $this->get(route('artists.show', ['name' => $name, 'page' => 2]))->assertInertia(fn ($page) => $page
            ->has('posts', 1)->where('nextPage', null));
    }
}
