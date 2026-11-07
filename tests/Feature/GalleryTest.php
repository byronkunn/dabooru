<?php

namespace Tests\Feature;

use App\Models\Media;
use App\Models\Post;
use App\Models\PostMedia;
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

    public function test_duration_filter_keeps_only_videos_in_range(): void
    {
        $artist = User::factory()->create();
        $make = function (int $seconds) use ($artist) {
            $media = Media::create([
                'user_id' => $artist->id, 'disk' => 'public', 'path' => 'uploads/x.mp4',
                'kind' => 'video', 'status' => 'ready', 'mime_type' => 'video/mp4',
                'duration_seconds' => $seconds,
            ]);
            $post = Post::create(['user_id' => $artist->id, 'type' => 'video', 'rating' => 'sfw', 'title' => "Clip {$seconds}s"]);
            PostMedia::create(['post_id' => $post->id, 'media_id' => $media->id, 'position' => 0]);

            return $post;
        };
        $short = $make(5);
        $long = $make(30);
        $image = Post::create(['user_id' => $artist->id, 'type' => 'image', 'rating' => 'sfw', 'title' => 'Still']);

        // no bounds: everything is returned
        $this->get('/?window=all')
            ->assertOk()
            ->assertInertia(fn ($page) => $page->has('posts', 3));

        // min 10s drops the 5s clip and any work without a duration
        $this->get('/?window=all&min_duration=10')
            ->assertOk()
            ->assertInertia(fn ($page) => $page
                ->has('posts', 1)
                ->where('posts.0.id', $long->id)
                ->where('filters.min_duration', 10));

        // an upper bound keeps only the short clip
        $this->get('/?window=all&max_duration=10')
            ->assertOk()
            ->assertInertia(fn ($page) => $page
                ->has('posts', 1)
                ->where('posts.0.id', $short->id));
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
