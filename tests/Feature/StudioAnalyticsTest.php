<?php

namespace Tests\Feature;

use App\Models\Block;
use App\Models\Comic;
use App\Models\ComicChapter;
use App\Models\ComicPage;
use App\Models\Follow;
use App\Models\Like;
use App\Models\Media;
use App\Models\Post;
use App\Models\PostRating;
use App\Models\Tag;
use App\Models\User;
use App\Support\StudioTracking;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class StudioAnalyticsTest extends TestCase
{
    use RefreshDatabase;

    private function work(User $owner, array $attributes = []): Post
    {
        return Post::create($attributes + [
            'user_id' => $owner->id, 'type' => 'image', 'rating' => 'sfw', 'is_original' => true, 'title' => 'Test work',
        ]);
    }

    public function test_dashboard_is_private_and_original_and_shared_metrics_stay_separate(): void
    {
        [$owner, $other] = User::factory()->count(2)->create();
        $original = $this->work($owner, ['views' => 10, 'likes_count' => 2]);
        $shared = $this->work($owner, ['artist_name' => 'External Artist', 'is_original' => false, 'views' => 30]);
        $foreign = $this->work($other, ['views' => 1000]);
        $this->work($owner, ['is_draft' => true, 'views' => 500]);
        $this->work($owner, ['views' => 500])->delete();
        $this->get(route('studio.index'))->assertRedirect(route('login'));
        $this->actingAs($owner)->get(route('studio.index'))->assertInertia(fn ($page) => $page
            ->where('analytics.lifetime.works', 2)->where('analytics.lifetime.views', 40)->missing('performance')->missing('plans'));
        $this->get(route('studio.index', ['tab' => 'posts']))->assertInertia(fn ($page) => $page
            ->has('performance', 2)->missing('analytics'));
        $this->get(route('studio.index', ['ownership' => 'original']))->assertInertia(fn ($page) => $page
            ->where('analytics.lifetime.views', 10));
        $this->get(route('studio.index', ['tab' => 'posts', 'ownership' => 'original']))->assertInertia(fn ($page) => $page
            ->has('performance', 1)->where('performance.0.id', $original->id));
        $this->get(route('studio.index', ['ownership' => 'shared']))->assertInertia(fn ($page) => $page
            ->where('analytics.lifetime.views', 30));
        $this->get(route('studio.index', ['tab' => 'posts', 'ownership' => 'shared']))->assertInertia(fn ($page) => $page
            ->where('performance.0.original', false)->where('performance.0.id', $shared->id));
        $this->get(route('studio.index', ['post' => $foreign->id]))->assertNotFound();
        $this->get(route('studio.index', ['tab' => 'fake']))->assertSessionHasErrors('tab');
        $this->get(route('studio.index', ['days' => 120]))->assertSessionHasErrors('days');
    }

    public function test_period_series_comparisons_top_posts_and_sources_use_recorded_events(): void
    {
        $this->freezeTime();
        [$owner, $reader] = User::factory()->count(2)->create();
        $old = $this->work($owner, ['likes_count' => 9999]);
        $best = $this->work($owner);
        Like::create(['user_id' => $reader->id, 'post_id' => $best->id]);
        $best->bookmarkedBy()->attach($reader);
        PostRating::create(['user_id' => $reader->id, 'post_id' => $best->id, 'stars' => 4]);
        $best->comments()->create(['user_id' => $reader->id, 'body' => 'Great work']);
        DB::table('post_views')->insert([
            ['post_id' => $best->id, 'viewer_key' => 'u'.$reader->id, 'viewed_on' => now()->toDateString(), 'source' => 'discover', 'created_at' => now(), 'updated_at' => now()],
            ['post_id' => $old->id, 'viewer_key' => 'older', 'viewed_on' => now()->subDays(8)->toDateString(), 'source' => 'unknown', 'created_at' => now()->subDays(8), 'updated_at' => now()->subDays(8)],
        ]);
        $this->actingAs($owner)->get(route('studio.index', ['days' => 7]))->assertInertia(fn ($page) => $page
            ->where('analytics.metrics.views.total', 1)->where('analytics.metrics.views.previous', 1)
            ->where('analytics.metrics.likes.total', 1)->where('analytics.metrics.saves.total', 1)
            ->where('analytics.metrics.comments.total', 1)->where('analytics.metrics.ratings.total', 1)
            ->has('analytics.dates', 7)->where('analytics.metrics.views.daily.6', 1)
            ->where('analytics.uniqueMembers', 1)->where('analytics.sources.0.source', 'discover')
            ->where('analytics.topPosts.0.id', $best->id)->where('analytics.topPosts.0.rating', 4)
            ->where('analytics.topPosts.0.interactionsPer100Views', 300)
            ->where('analytics.postingSuggestion', null));
        $this->get(route('studio.index', ['post' => $old->id, 'days' => 7, 'tab' => 'analytics']))->assertInertia(fn ($page) => $page
            ->where('analytics.metrics.likes.total', 0)->where('analytics.metrics.views.total', 0));
        $best->comments()->firstOrFail()->delete();
        $this->get(route('studio.index', ['post' => $best->id]))->assertInertia(fn ($page) => $page
            ->where('analytics.metrics.comments.total', 0));
    }

    public function test_follow_gains_losses_and_blocking_are_counted_once(): void
    {
        [$owner, $reader] = User::factory()->count(2)->create();
        $this->actingAs($reader)->post(route('users.follow', $owner->username));
        $this->post(route('users.follow', $owner->username));
        $this->delete(route('users.unfollow', $owner->username));
        $this->delete(route('users.unfollow', $owner->username));
        $this->assertSame(2, DB::table('follower_changes')->where('user_id', $owner->id)->count());
        $this->actingAs($owner)->get(route('studio.index'))->assertInertia(fn ($page) => $page
            ->where('analytics.metrics.followers.total', 0)->where('analytics.lifetime.followers', 0));
        Follow::create(['follower_id' => $reader->id, 'following_id' => $owner->id]);
        $this->actingAs($owner)->post(route('users.block', $reader->username));
        $this->get(route('studio.index'))->assertInertia(fn ($page) => $page->where('analytics.metrics.followers.total', 0));
        $departing = User::factory()->create();
        Follow::create(['follower_id' => $departing->id, 'following_id' => $owner->id]);
        $departing->delete();
        $this->get(route('studio.index'))->assertInertia(fn ($page) => $page
            ->where('analytics.metrics.followers.total', 0)->where('analytics.lifetime.followers', 0));
    }

    public function test_post_sources_store_categories_not_external_hosts_and_deduplicate_daily_views(): void
    {
        $this->withCredentials();
        [$owner, $reader] = User::factory()->count(2)->create();
        $post = $this->work($owner);
        $token = $this->actingAs($reader)->withHeader('referer', url('/discover'))->get(route('posts.show', $post))->assertOk()->viewData('page')['props']['viewToken'];
        $this->travel(2)->seconds();
        $this->postJson(route('posts.track', $post), ['token' => $token, 'event' => 'view'])->assertOk();
        $this->postJson(route('posts.track', $post), ['token' => $token, 'event' => 'view'])->assertOk();
        $this->assertDatabaseCount('post_views', 1);
        $this->assertDatabaseHas('post_views', ['post_id' => $post->id, 'source' => 'discover']);
        $this->travel(1)->days();
        $token = $this->withHeader('referer', 'https://private.example/sensitive?q=secret')->get(route('posts.show', $post))->assertOk()->viewData('page')['props']['viewToken'];
        $this->travel(2)->seconds();
        $this->postJson(route('posts.track', $post), ['token' => $token, 'event' => 'view'])->assertOk();
        $this->assertDatabaseHas('post_views', ['source' => 'external']);
        $this->assertDatabaseCount('post_views', 2);
    }

    public function test_comic_tracking_preserves_maximum_page_and_aggregates_without_viewer_identity(): void
    {
        [$owner, $reader] = User::factory()->count(2)->create();
        $comic = Comic::create(['user_id' => $owner->id, 'slug' => 'analytics-comic', 'title' => 'Analytics comic', 'rating' => 'sfw']);
        $chapter = ComicChapter::create(['comic_id' => $comic->id, 'number' => 1, 'rating' => 'sfw', 'published_at' => now()]);
        for ($i = 1; $i <= 3; $i++) {
            $media = Media::create(['user_id' => $owner->id, 'path' => "pages/{$i}.jpg", 'kind' => 'image', 'width' => 800, 'height' => 1200]);
            ComicPage::create(['comic_chapter_id' => $chapter->id, 'media_id' => $media->id, 'position' => $i]);
        }
        $this->actingAs($reader)->get(route('comics.read', [$comic->slug, 1]))->assertOk();
        $this->postJson(route('comics.progress', $comic), ['chapter_id' => $chapter->id, 'page' => 3])->assertOk();
        $this->postJson(route('comics.progress', $comic), ['chapter_id' => $chapter->id, 'page' => 1])->assertOk();
        $this->assertDatabaseHas('chapter_readers', ['max_page' => 3]);
        $this->actingAs($owner)->get(route('studio.index', ['tab' => 'comics']))->assertInertia(fn ($page) => $page
            ->has('comicAnalytics', 1)->where('comicAnalytics.0.chapters.0.readers', 1)
            ->where('comicAnalytics.0.chapters.0.completed', 1)
            ->where('comicAnalytics.0.chapters.0.completionRate', 100)
            ->missing('comicAnalytics.0.chapters.0.viewer_key'));
    }

    public function test_posting_suggestions_require_the_exact_sample_threshold_and_ownership_filter(): void
    {
        $this->freezeTime();
        $owner = User::factory()->create();
        $reader = User::factory()->create();
        for ($i = 0; $i < 10; $i++) {
            $post = $this->work($owner);
            Tag::syncFor($post, ['planning']);
            Like::create(['post_id' => $post->id, 'user_id' => $reader->id]);
            if ($i < 9) Like::create(['post_id' => $post->id, 'user_id' => User::factory()->create()->id]);
        }
        $this->actingAs($owner)->get(route('studio.index'))->assertInertia(fn ($page) => $page
            ->where('analytics.postingSuggestion', null)->where('analytics.breakdowns.tags.0.likes', 19));
        $post = Post::latest('id')->first();
        Like::create(['post_id' => $post->id, 'user_id' => User::factory()->create()->id]);
        $this->get(route('studio.index'))->assertInertia(fn ($page) => $page
            ->where('analytics.postingSuggestion.posts', 10)->where('analytics.postingSuggestion.averageLikes', 2));
        $this->get(route('studio.index', ['ownership' => 'shared']))->assertInertia(fn ($page) => $page
            ->where('analytics.postingSuggestion', null)->has('analytics.breakdowns.tags', 0));
    }

    public function test_ranking_changes_by_views_saves_and_ratings_and_series_pagination_is_private(): void
    {
        [$owner, $reader, $other] = User::factory()->count(3)->create();
        $popular = $this->work($owner);
        $saved = $this->work($owner);
        $rated = $this->work($owner);
        DB::table('post_views')->insert(['post_id' => $popular->id, 'viewer_key' => 'u'.$reader->id, 'viewed_on' => now()->toDateString(), 'source' => 'gallery']);
        $saved->bookmarkedBy()->attach($reader);
        PostRating::create(['post_id' => $rated->id, 'user_id' => $reader->id, 'stars' => 5]);
        foreach (['views' => $popular, 'saves' => $saved, 'ratings' => $rated] as $sort => $post) {
            $this->actingAs($owner)->get(route('studio.index', ['tab' => 'posts', 'sort' => $sort]))->assertInertia(fn ($page) => $page
                ->where('performance.0.id', $post->id));
        }
        for ($i = 0; $i < 11; $i++) Comic::create(['user_id' => $owner->id, 'slug' => "owned-{$i}", 'title' => 'Owned', 'rating' => 'sfw']);
        Comic::create(['user_id' => $other->id, 'slug' => 'foreign', 'title' => 'Foreign', 'rating' => 'sfw']);
        $this->get(route('studio.index', ['tab' => 'comics', 'comics_page' => 2]))->assertInertia(fn ($page) => $page
            ->has('comicAnalytics', 1)->where('comicAnalytics.0.title', 'Owned'));
    }
}
