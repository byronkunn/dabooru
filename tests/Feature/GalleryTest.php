<?php

namespace Tests\Feature;

use App\Models\Post;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class GalleryTest extends TestCase
{
    use RefreshDatabase;

    private function seedPosts(): array
    {
        $artist = User::factory()->create(['username' => 'artist']);

        $sfw = Post::create(['user_id' => $artist->id, 'type' => 'image', 'rating' => 'sfw', 'title' => 'Safe', 'views' => 100, 'likes_count' => 5]);
        $explicit = Post::create(['user_id' => $artist->id, 'type' => 'image', 'rating' => 'explicit', 'title' => 'Adult', 'views' => 900, 'likes_count' => 50]);
        $video = Post::create(['user_id' => $artist->id, 'type' => 'video', 'rating' => 'sfw', 'title' => 'Clip', 'views' => 50, 'likes_count' => 1]);

        return compact('sfw', 'explicit', 'video');
    }

    public function test_gallery_hides_explicit_by_default_for_guests(): void
    {
        ['sfw' => $sfw, 'explicit' => $explicit] = $this->seedPosts();

        $this->get('/')
            ->assertOk()
            ->assertInertia(fn ($page) => $page
                ->component('Gallery/Index')
                ->has('posts', 2)
                ->where('filters.format', 'all')
                ->where('filters.sort', 'top')
                ->where('filters.explicit', false));
    }

    public function test_gallery_filters_by_format(): void
    {
        ['video' => $video] = $this->seedPosts();

        $this->get('/?format=video')
            ->assertOk()
            ->assertInertia(fn ($page) => $page
                ->has('posts', 1)
                ->where('posts.0.id', $video->id)
                ->where('filters.format', 'video'));
    }

    public function test_gallery_sorts_by_views(): void
    {
        ['sfw' => $sfw, 'video' => $video] = $this->seedPosts();

        $this->get('/?sort=views')
            ->assertOk()
            ->assertInertia(fn ($page) => $page
                ->where('posts.0.id', $sfw->id)
                ->where('posts.1.id', $video->id));
    }

    public function test_gallery_sorts_by_newest(): void
    {
        ['video' => $video] = $this->seedPosts();

        $this->get('/?sort=newest')
            ->assertOk()
            ->assertInertia(fn ($page) => $page->where('posts.0.id', $video->id));
    }

    public function test_invalid_filters_fall_back_to_defaults(): void
    {
        $this->get('/?format=banana&sort=garbage&window=nonsense')
            ->assertOk()
            ->assertInertia(fn ($page) => $page
                ->where('filters.format', 'all')
                ->where('filters.sort', 'top')
                ->where('filters.window', 'week'));
    }

    public function test_logged_in_user_with_explicit_enabled_sees_explicit_posts(): void
    {
        ['explicit' => $explicit] = $this->seedPosts();

        $user = User::factory()->create(['show_explicit' => true]);

        $this->actingAs($user)
            ->get('/')
            ->assertOk()
            ->assertInertia(fn ($page) => $page->has('posts', 3));
    }
}
