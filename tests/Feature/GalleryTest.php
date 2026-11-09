<?php

namespace Tests\Feature;

use App\Models\Block;
use App\Models\Like;
use App\Models\Media;
use App\Models\Post;
use App\Models\PostMedia;
use App\Models\PostRating;
use App\Models\Tag;
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

    public function test_sidebar_discovery_uses_recent_artist_activity_and_visible_weekly_likes(): void
    {
        $viewer = User::factory()->create(['show_explicit' => false]);
        $rising = User::factory()->create(['name' => 'Rising artist']);
        $inactive = User::factory()->create(['name' => 'Inactive artist']);
        $blocked = User::factory()->create(['name' => 'Blocked artist']);
        $olderPost = Post::create(['user_id' => $rising->id, 'type' => 'image', 'rating' => 'sfw', 'title' => 'Older upload']);
        $olderPost->forceFill(['created_at' => now()->subDays(45)])->save();
        $firstRecent = Post::create(['user_id' => $rising->id, 'type' => 'image', 'rating' => 'sfw', 'title' => 'Recent upload one']);
        Post::create(['user_id' => $rising->id, 'type' => 'image', 'rating' => 'sfw', 'title' => 'Recent upload two']);
        $inactivePost = Post::create(['user_id' => $inactive->id, 'type' => 'image', 'rating' => 'sfw', 'title' => 'Inactive upload']);
        $inactivePost->forceFill(['created_at' => now()->subDays(45)])->save();
        $blockedPost = Post::create(['user_id' => $blocked->id, 'type' => 'image', 'rating' => 'sfw', 'title' => 'Blocked upload']);
        $popular = Post::create(['user_id' => $rising->id, 'type' => 'image', 'rating' => 'sfw', 'title' => 'Popular']);
        $lessPopular = Post::create(['user_id' => $inactive->id, 'type' => 'image', 'rating' => 'sfw', 'title' => 'Less popular']);
        $cachedPopular = Post::create(['user_id' => $inactive->id, 'type' => 'image', 'rating' => 'sfw', 'title' => 'Cached popularity', 'likes_count' => 2]);
        $explicit = Post::create(['user_id' => $rising->id, 'type' => 'image', 'rating' => 'explicit', 'title' => 'Hidden explicit']);
        foreach ([[$popular, 3], [$lessPopular, 1], [$blockedPost, 5], [$explicit, 7]] as [$post, $likes]) {
            for ($i = 0; $i < $likes; $i++) {
                Like::create(['user_id' => User::factory()->create()->id, 'post_id' => $post->id]);
            }
        }
        Like::create(['user_id' => User::factory()->create()->id, 'post_id' => $olderPost->id])
            ->forceFill(['created_at' => now()->subDays(10)])->save();
        Block::create(['blocker_id' => $viewer->id, 'blocked_id' => $blocked->id]);

        $this->actingAs($viewer)->get('/')->assertInertia(fn ($page) => $page
            ->has('discoveryRail.risingArtists', 2)->where('discoveryRail.risingArtists.0.username', $rising->username)
            ->where('discoveryRail.risingArtists.0.recent_posts_count', 3)
            ->has('discoveryRail.popularWorks', 3)->where('discoveryRail.popularWorks.0.id', $popular->id)
            ->where('discoveryRail.popularWorks.1.id', $lessPopular->id)
            ->where('discoveryRail.popularWorks.2.id', $cachedPopular->id)
            ->where('discoveryRail.popularWorks.2.lifetime_likes', 2));
        $this->get(route('profile.show', $rising->username))
            ->assertInertia(fn ($page) => $page->has('discoveryRail.risingArtists', 2));
    }

    public function test_sidebar_respects_rating_preference_and_gallery_override(): void
    {
        $viewer = User::factory()->create(['show_explicit' => true]);
        $mixedArtist = User::factory()->create(['name' => 'Mixed artist']);
        $explicitArtist = User::factory()->create(['name' => 'Explicit artist']);
        $safe = Post::create(['user_id' => $mixedArtist->id, 'type' => 'image', 'rating' => 'sfw', 'title' => 'Safe', 'likes_count' => 1]);
        Post::create(['user_id' => $mixedArtist->id, 'type' => 'image', 'rating' => 'explicit', 'title' => 'Explicit', 'likes_count' => 3]);
        Post::create(['user_id' => $explicitArtist->id, 'type' => 'image', 'rating' => 'explicit', 'title' => 'Explicit only', 'likes_count' => 2]);

        $this->actingAs($viewer)->get('/?explicit=0')->assertInertia(fn ($page) => $page
            ->where('filters.explicit', false)
            ->has('discoveryRail.risingArtists', 1)
            ->where('discoveryRail.risingArtists.0.username', $mixedArtist->username)
            ->where('discoveryRail.risingArtists.0.recent_posts_count', 1)
            ->has('discoveryRail.popularWorks', 1)
            ->where('discoveryRail.popularWorks.0.id', $safe->id));

        $this->get('/?explicit=1')->assertInertia(fn ($page) => $page
            ->has('discoveryRail.risingArtists', 2)
            ->where('discoveryRail.risingArtists.0.recent_posts_count', 2)
            ->has('discoveryRail.popularWorks', 3));

        $viewer->update(['show_explicit' => false]);
        $this->get('/?explicit=1')->assertInertia(fn ($page) => $page
            ->where('filters.explicit', true)
            ->has('discoveryRail.risingArtists', 1)
            ->where('discoveryRail.risingArtists.0.recent_posts_count', 1)
            ->has('discoveryRail.popularWorks', 1));

        $viewer->update(['show_explicit' => true]);
        $this->get(route('profile.show', ['username' => $mixedArtist->username, 'explicit' => 0]))
            ->assertInertia(fn ($page) => $page
                ->has('discoveryRail.risingArtists', 2)
                ->has('discoveryRail.popularWorks', 3));
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

    public function test_tag_include_and_exclude_filters(): void
    {
        $artist = User::factory()->create();
        $tagged = function (string $title, array $tags) use ($artist) {
            $post = Post::create(['user_id' => $artist->id, 'type' => 'image', 'rating' => 'sfw', 'title' => $title]);
            Tag::syncFor($post, $tags);

            return $post;
        };
        $watercolor = $tagged('Water', ['watercolor']);
        $fanart = $tagged('Fan', ['fanart']);
        $both = $tagged('Both', ['watercolor', 'fanart']);
        $plain = $tagged('Plain', ['sketch']);

        // must include
        $this->get('/?window=all&tags=watercolor')->assertOk()
            ->assertInertia(fn ($page) => $page->has('posts', 2)->where('filters.tags', ['watercolor']));

        // must exclude
        $this->get('/?window=all&exclude_tags=watercolor')->assertOk()
            ->assertInertia(fn ($page) => $page->has('posts', 2)->where('filters.exclude_tags', ['watercolor']));

        // the array shape Inertia emits must behave the same as the comma shape
        $this->get('/?window=all&exclude_tags[0]=watercolor')->assertOk()
            ->assertInertia(fn ($page) => $page->has('posts', 2)->where('filters.exclude_tags', ['watercolor']));

        // include + exclude together
        $this->get('/?window=all&tags=fanart&exclude_tags=watercolor')->assertOk()
            ->assertInertia(fn ($page) => $page->has('posts', 1)->where('posts.0.id', $fanart->id));
    }

    public function test_popularity_thresholds_keep_only_works_above_them(): void
    {
        $artist = User::factory()->create();
        Post::create(['user_id' => $artist->id, 'type' => 'image', 'rating' => 'sfw', 'title' => 'Fresh', 'likes_count' => 1, 'views' => 5]);
        $popular = Post::create(['user_id' => $artist->id, 'type' => 'image', 'rating' => 'sfw', 'title' => 'Popular', 'likes_count' => 40, 'views' => 300]);

        $this->get('/?window=all&min_likes=10')->assertOk()
            ->assertInertia(fn ($page) => $page->has('posts', 1)->where('posts.0.id', $popular->id)->where('filters.min_likes', 10));

        $this->get('/?window=all&min_views=100')->assertOk()
            ->assertInertia(fn ($page) => $page->has('posts', 1)->where('posts.0.id', $popular->id));

        // 0 means "no threshold", not "at least zero likes"
        $this->get('/?window=all&min_likes=0')->assertOk()
            ->assertInertia(fn ($page) => $page->has('posts', 2)->where('filters.min_likes', ''));
    }

    public function test_a_duration_filter_narrows_the_gallery_to_video(): void
    {
        $artist = User::factory()->create();
        Post::create(['user_id' => $artist->id, 'type' => 'image', 'rating' => 'sfw', 'title' => 'Still']);

        $this->get('/?window=all&format=image&min_duration=10')->assertOk()
            ->assertInertia(fn ($page) => $page
                ->where('filters.format', 'video')
                ->where('durationNarrowed', true));

        // without a duration bound the requested format is left alone
        $this->get('/?window=all&format=image')->assertOk()
            ->assertInertia(fn ($page) => $page->where('filters.format', 'image')->where('durationNarrowed', false));
    }

    public function test_star_filter_uses_the_average_and_any_includes_unrated_works(): void
    {
        $artist = User::factory()->create();
        $voters = User::factory()->count(3)->create();
        $make = function (array $scores, string $rating = 'sfw') use ($artist, $voters) {
            $post = Post::create(['user_id' => $artist->id, 'type' => 'image', 'rating' => $rating]);
            foreach ($scores as $index => $stars) {
                PostRating::create(['post_id' => $post->id, 'user_id' => $voters[$index]->id, 'stars' => $stars]);
            }

            return $post;
        };
        $unrated = $make([]);
        $below = $make([5, 3, 3]);
        $boundary = $make([5, 3]);
        $perfect = $make([5, 5]);
        $make([5], 'explicit');

        $this->get('/?window=all&min_stars=4')->assertOk()->assertInertia(fn ($page) => $page
            ->where('filters.min_stars', 4)->has('posts', 2)
            ->where('posts.0.id', $perfect->id)->where('posts.1.id', $boundary->id));
        $this->getJson('/?window=all&min_stars=5')->assertOk()
            ->assertJsonCount(1, 'posts')->assertJsonPath('posts.0.id', $perfect->id);
        $this->getJson('/?window=all&min_stars=1')->assertOk()->assertJsonCount(3, 'posts');
        $this->get('/?window=all')->assertInertia(fn ($page) => $page
            ->where('filters.min_stars', '')->has('posts', 4));
        foreach (['0', '-1', '6', '4.5', 'invalid', ''] as $invalid) {
            $this->get('/?window=all&min_stars='.$invalid)->assertInertia(fn ($page) => $page
                ->where('filters.min_stars', '')->has('posts', 4));
        }
    }

    public function test_star_filter_combines_with_tags_format_sort_and_pagination(): void
    {
        $artist = User::factory()->create();
        $voter = User::factory()->create();
        $ids = [];
        for ($i = 0; $i < 40; $i++) {
            $post = Post::create(['user_id' => $artist->id, 'type' => 'image', 'rating' => 'sfw', 'views' => $i]);
            Tag::syncFor($post, ['study']);
            PostRating::create(['post_id' => $post->id, 'user_id' => $voter->id, 'stars' => 4]);
            $ids[] = $post->id;
        }
        $query = '/?window=all&format=image&tags=study&sort=views&min_stars=4';
        $this->get($query)->assertInertia(fn ($page) => $page
            ->where('filters.min_stars', 4)->where('filters.sort', 'views')
            ->has('posts', 36)->where('posts.0.id', $ids[39])->where('hasMore', true));
        $this->getJson($query.'&page=2')->assertOk()->assertJsonCount(4, 'posts')
            ->assertJsonPath('posts.0.id', $ids[3])->assertJsonPath('hasMore', false);
        $this->getJson($query.'&format=video')->assertJsonCount(0, 'posts');
        $this->getJson($query.'&exclude_tags=study')->assertJsonCount(0, 'posts');
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

    public function test_zero_duration_bounds_mean_no_duration_filter(): void
    {
        $this->seedPosts();
        $this->get('/?min_duration=0&max_duration=0')
            ->assertInertia(fn ($page) => $page->has('posts', 2)
                ->where('filters.format', 'all')
                ->where('filters.min_duration', '')
                ->where('filters.max_duration', ''));
    }

    public function test_long_video_filters_and_sort_apply_before_pagination(): void
    {
        $artist = User::factory()->create();
        $ids = [];
        for ($i = 0; $i < 40; $i++) {
            $post = Post::create([
                'user_id' => $artist->id, 'type' => 'video', 'rating' => 'sfw',
                'title' => 'Study', 'views' => 100 + $i, 'likes_count' => 10,
                'created_at' => now()->subDays(15),
            ]);
            $media = Media::create([
                'user_id' => $artist->id, 'disk' => 'public', 'path' => "uploads/long-{$i}.mp4",
                'kind' => 'video', 'status' => 'ready', 'duration_seconds' => 4000 + $i,
            ]);
            PostMedia::create(['post_id' => $post->id, 'media_id' => $media->id, 'position' => 0]);
            Tag::syncFor($post, ['study']);
            $ids[] = $post->id;
        }
        $query = '/?format=video&window=month&sort=views&tags=study&min_duration=1500&max_duration=5400&min_likes=10&min_views=100';
        $this->get($query)->assertInertia(fn ($page) => $page
            ->has('posts', 36)->where('posts.0.id', $ids[39])
            ->where('filters.sort', 'views')->where('filters.window', 'month')
            ->where('filters.min_duration', 1500)->where('filters.max_duration', 5400)
            ->where('hasMore', true));
        $this->getJson($query.'&page=2')->assertOk()
            ->assertJsonCount(4, 'posts')
            ->assertJsonPath('posts.0.id', $ids[3])
            ->assertJsonPath('page', 2)->assertJsonPath('hasMore', false);
        $this->get('/?format=video&window=all&min_duration=1500')->assertInertia(fn ($page) => $page
            ->has('posts', 36)->where('filters.max_duration', ''));
        $this->get($query.'&max_duration=3600')->assertInertia(fn ($page) => $page->has('posts', 0));
    }

    public function test_tied_sort_results_have_stable_pagination(): void
    {
        $artist = User::factory()->create();
        $ids = [];
        for ($i = 0; $i < 40; $i++) {
            $ids[] = Post::create([
                'user_id' => $artist->id, 'type' => 'image', 'rating' => 'sfw',
                'created_at' => now()->startOfDay(), 'likes_count' => 5, 'views' => 10,
            ])->id;
        }
        foreach (['top', 'views', 'newest'] as $sort) {
            $this->getJson('/?sort='.$sort)->assertJsonPath('posts.0.id', $ids[39])->assertJsonPath('hasMore', true);
            $this->getJson('/?sort='.$sort.'&page=2')->assertJsonCount(4, 'posts')->assertJsonPath('posts.0.id', $ids[3]);
        }
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
