<?php

namespace Tests\Feature;

use App\Models\ArtistCreditClaim;
use App\Models\Comic;
use App\Models\ComicChapter;
use App\Models\ComicFollow;
use App\Models\Post;
use App\Models\SavedCollection;
use App\Models\SavedSearch;
use App\Models\Tag;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Storage;
use Tests\Support\FakeImage;
use Tests\TestCase;

class ReaderAndCreatorFeaturesTest extends TestCase
{
    use RefreshDatabase;

    public function test_saved_search_applies_tags_and_rating_preferences_and_is_private(): void
    {
        $reader = User::factory()->create(['show_explicit' => false]);
        $other = User::factory()->create();
        $match = Post::create(['user_id' => $other->id, 'title' => 'Original work', 'type' => 'image', 'rating' => 'sfw']);
        $excluded = Post::create(['user_id' => $other->id, 'type' => 'image', 'rating' => 'sfw']);
        $explicit = Post::create(['user_id' => $other->id, 'type' => 'image', 'rating' => 'explicit']);
        Tag::syncFor($match, ['watercolor']);
        Tag::syncFor($excluded, ['watercolor', 'fanart']);
        Tag::syncFor($explicit, ['watercolor']);

        $this->actingAs($reader)->post(route('saved-searches.store'), [
            'name' => 'Original watercolor', 'q' => 'tag:watercolor -tag:fanart',
        ])->assertRedirect();
        $search = SavedSearch::firstOrFail();
        $url = route('saved-searches.show', $search);
        $this->get($url)->assertInertia(fn ($page) => $page
            ->component('Discovery/SavedSearches')->has('posts', 1)->where('posts.0.id', $match->id));

        $this->actingAs($other)->get($url)->assertNotFound();
        $this->delete($url)->assertNotFound();
    }

    public function test_saved_gallery_search_replays_filters_and_respects_library_mutes(): void
    {
        $reader = User::factory()->create(['show_explicit' => false]);
        $artist = User::factory()->create();
        $work = fn ($type, $rating, $likes) => Post::create([
            'user_id' => $artist->id, 'type' => $type, 'rating' => $rating, 'likes_count' => $likes,
        ]);
        $match = $work('image', 'sfw', 6);
        $muted = $work('image', 'sfw', 10);
        Tag::syncFor($match, ['watercolor']);
        Tag::syncFor($muted, ['sketch', 'spoilers']);
        Tag::syncFor($work('image', 'sfw', 1), ['watercolor']);
        Tag::syncFor($work('video', 'sfw', 8), ['watercolor']);
        Tag::syncFor($work('image', 'explicit', 8), ['watercolor']);

        $this->actingAs($reader)->post(route('saved-searches.store'), [
            'name' => 'Popular images',
            'gallery' => ['format' => 'image', 'tags' => ['watercolor', 'sketch'], 'min_likes' => 5, 'window' => 'all', 'explicit' => true],
        ])->assertSessionHasNoErrors();
        $url = route('saved-searches.show', SavedSearch::firstOrFail());
        $this->get($url)->assertInertia(fn ($page) => $page->has('posts', 2)
            ->where('posts.0.id', $muted->id)->where('posts.1.id', $match->id)
            ->where('selected.filters.gallery.format', 'image')->where('selected.filters.gallery.min_likes', 5));

        $this->from($url)->patch(route('library.muted-tags.update'), ['exclude_tags' => 'spoilers'])->assertRedirect($url);
        $this->get($url)->assertInertia(fn ($page) => $page->has('posts', 1)
            ->where('posts.0.id', $match->id)->where('mutedTags', ['spoilers']));
    }

    public function test_work_drafts_keep_media_and_tags_private_until_the_owner_publishes(): void
    {
        Storage::fake('local');
        Storage::fake('public');
        $artist = User::factory()->create();
        $visitor = User::factory()->create();
        $tags = 'private-tag, '.implode(', ', array_map(fn ($number) => str_repeat('x', 35).$number, range(1, 29)));
        $this->actingAs($artist)->post(route('upload.store'), [
            'type' => 'album', 'title' => 'Private album', 'rating' => 'sfw', 'draft' => true,
            'tags' => $tags, 'files' => [FakeImage::upload('one.png'), FakeImage::upload('two.png')],
        ])->assertRedirect(route('studio.index'));

        $draft = Post::withoutGlobalScope('published')->firstOrFail();
        $this->assertCount(30, $draft->draft_tags);
        $items = $draft->media()->with('media')->get();
        $media = $items->first()->media;
        Storage::disk('local')->assertExists($media->path);
        Storage::disk('public')->assertMissing($media->path);
        $this->assertDatabaseMissing('tags', ['name' => 'private-tag']);
        $this->get(route('studio.index', ['tab' => 'posts']))->assertInertia(fn ($page) => $page->component('Studio/Index')->where('works.0.is_draft', true));
        $this->get($media->url())->assertOk();

        $input = [
            'title' => $draft->title, 'caption' => null, 'rating' => 'sfw', 'tags' => $tags,
            'order' => $items->pluck('id')->reverse()->values()->all(), 'publish' => true,
        ];
        $this->actingAs($visitor)->get($media->url())->assertNotFound();
        $this->get(route('posts.show', $draft->id))->assertNotFound();
        $this->patch(route('studio.works.update', $draft->id), $input)->assertNotFound();

        $this->actingAs($artist)->patch(route('studio.works.update', $draft->id), $input)->assertRedirect();
        $this->assertSame($items->last()->id, $draft->media()->first()->id);
        $this->actingAs($visitor)->get(route('posts.show', $draft->id))->assertOk();
        $this->get($media->url())->assertOk();
        $this->assertDatabaseHas('tags', ['name' => 'private-tag']);
        $this->assertSame(30, $draft->tags()->count());
    }

    public function test_unpublished_chapter_stays_private_and_completion_clears_the_inbox_item(): void
    {
        Storage::fake('local');
        $artist = User::factory()->create();
        $reader = User::factory()->create(['show_explicit' => false]);
        $series = Comic::create(['user_id' => $artist->id, 'slug' => 'ink-comic', 'title' => 'Ink Comic', 'rating' => 'sfw']);
        $tags = 'page-tag, '.implode(', ', array_map(fn ($number) => str_repeat('y', 35).$number, range(1, 29)));
        ComicFollow::create(['user_id' => $reader->id, 'comic_id' => $series->id]);
        $this->actingAs($artist)->post(route('comics.chapters.store', $series), [
            'number' => 1, 'title' => 'Start', 'rating' => 'sfw', 'publish' => false,
            'tags' => $tags, 'pages' => [FakeImage::upload('first.png'), FakeImage::upload('second.png')],
        ])->assertRedirect(route('studio.index'));
        $chapter = ComicChapter::firstOrFail();
        $this->assertCount(30, $chapter->draft_tags);
        $pages = $chapter->pages()->with('media')->get();
        $readUrl = route('comics.read', [$series->slug, 1]);
        $this->actingAs($reader)->get($readUrl)->assertNotFound();
        $this->get($pages->first()->media->url())->assertNotFound();
        $this->get(route('comics.show', $series->slug))->assertInertia(fn ($page) => $page->has('comic.chapters', 0));
        $this->get(route('chapters.inbox'))->assertInertia(fn ($page) => $page->has('chapters', 0));

        $input = ['title' => 'Start', 'rating' => 'sfw', 'tags' => $tags,
            'order' => $pages->pluck('id')->reverse()->values()->all(), 'publish' => true];
        $publish = route('studio.chapters.update', $chapter->id);
        $this->actingAs($artist)->patch($publish, $input)->assertRedirect();
        $this->patch($publish, $input)->assertNotFound();
        $this->assertSame(1, Post::where('comic_chapter_id', $chapter->id)->count());
        $this->assertSame(30, Post::where('comic_chapter_id', $chapter->id)->firstOrFail()->tags()->count());
        $this->assertSame($pages->last()->id, $chapter->pages()->first()->id);

        $this->actingAs($reader)->get(route('chapters.inbox'))->assertInertia(fn ($page) => $page->has('chapters', 1));
        $this->postJson(route('comics.progress', $series), ['chapter_id' => $chapter->id, 'page' => 2])->assertOk();
        $this->get(route('chapters.inbox'))->assertInertia(fn ($page) => $page->has('chapters', 0));
    }

    public function test_collection_link_can_be_revoked_and_rotated(): void
    {
        $owner = User::factory()->create();
        $reader = User::factory()->create(['show_explicit' => false]);
        $collection = SavedCollection::create(['user_id' => $owner->id, 'name' => 'References']);
        $post = Post::create(['user_id' => $owner->id, 'type' => 'image', 'rating' => 'sfw']);
        $explicit = Post::create(['user_id' => $owner->id, 'type' => 'image', 'rating' => 'explicit']);
        $share = route('collections.share', $collection);

        $this->actingAs($owner)->post(route('collections.posts.add', [$collection, $post]))->assertRedirect();
        $this->post(route('collections.posts.add', [$collection, $explicit]))->assertRedirect();
        $this->patch($share, ['shared' => true])->assertRedirect();
        $oldLink = route('collections.shared', $collection->fresh()->share_token);

        $this->actingAs($reader)->get($oldLink)->assertInertia(fn ($page) => $page
            ->component('Collections/Shared')->has('posts', 1)->where('posts.0.id', $post->id));
        $this->patch($share, ['shared' => false])->assertNotFound();
        $this->actingAs($owner)->patch($share, ['shared' => false])->assertRedirect();
        $this->get($oldLink)->assertNotFound();
        $this->patch($share, ['shared' => true])->assertRedirect();
        $this->get($oldLink)->assertNotFound();
    }

    public function test_collection_folders_can_be_private_or_shared_on_creation_and_are_owner_managed(): void
    {
        $owner = User::factory()->create();
        $other = User::factory()->create();
        $this->actingAs($owner)->post(route('collections.store'), ['name' => 'Private references'])->assertRedirect();
        $private = SavedCollection::firstOrFail();
        $this->assertNull($private->share_token);
        $this->post(route('collections.store'), ['name' => 'Shared references', 'shared' => true])->assertRedirect();
        $shared = SavedCollection::where('name', 'Shared references')->firstOrFail();
        $this->assertNotNull($shared->share_token);
        $this->get(route('collections.index'))->assertInertia(fn ($page) => $page
            ->where('collections.0.shared', false)->where('collections.1.shared', true));

        $this->post(route('collections.store'), ['name' => 'Private references', 'shared' => true])->assertRedirect();
        $this->assertNull($private->fresh()->share_token);
        $this->assertDatabaseCount('saved_collections', 2);
        $this->post(route('collections.store'), ['name' => 'Bad visibility', 'shared' => 'public'])->assertSessionHasErrors('shared');
        $post = Post::create(['user_id' => $owner->id, 'type' => 'video', 'rating' => 'sfw']);
        $this->post(route('collections.posts.add', [$shared, $post]))->assertRedirect();
        $this->post(route('collections.posts.add', [$shared, $post]))->assertRedirect();
        $this->assertDatabaseCount('bookmark_saved_collection', 1);

        $this->actingAs($other)->get(route('collections.show', $shared))->assertNotFound();
        $this->patch(route('collections.update', $shared), ['name' => 'Hijacked'])->assertNotFound();
        $this->delete(route('collections.destroy', $shared))->assertNotFound();
        $this->post(route('collections.posts.add', [$shared, $post]))->assertNotFound();
        $this->delete(route('collections.posts.remove', [$shared, $post]))->assertNotFound();
        $link = route('collections.shared', $shared->share_token);
        $this->get($link)->assertInertia(fn ($page) => $page->has('posts', 1));
        $this->actingAs($owner)->patch(route('collections.update', $shared), ['name' => 'Renamed folder'])->assertRedirect();
        $this->assertSame('Renamed folder', $shared->fresh()->name);
        $this->delete(route('collections.posts.remove', [$shared, $post]))->assertRedirect();
        $this->get($link)->assertInertia(fn ($page) => $page->has('posts', 0));
        $this->post(route('collections.posts.add', [$shared, $post]))->assertRedirect();
        $this->delete(route('collections.destroy', $shared))->assertRedirect(route('collections.index'));
        $this->get($link)->assertNotFound();
        $this->assertDatabaseCount('bookmarks', 1);
        $this->assertDatabaseHas('posts', ['id' => $post->id, 'deleted_at' => null]);
    }

    public function test_curated_shelves_and_artist_credit_require_curator_access(): void
    {
        $claimant = User::factory()->create();
        $curator = User::factory()->create();
        $curator->forceFill(['staff_roles' => ['curator']])->save();
        $post = Post::create([
            'user_id' => $curator->id, 'type' => 'image', 'rating' => 'sfw',
            'artist_name' => 'Ink Artist', 'source_url' => 'https://example.test/original', 'is_original' => false,
        ]);

        $shelf = ['title' => 'Staff picks', 'published' => true, 'entryText' => '/p/'.$post->id."\n/u/".$claimant->username];
        $this->actingAs($claimant)->post(route('admin.shelves.store'), $shelf)->assertForbidden();
        $this->post(route('credits.store', $post), ['evidence' => 'My portfolio has the original upload.'])->assertRedirect();
        $claim = ArtistCreditClaim::firstOrFail();
        $decision = ['decision' => 'approved', 'reason' => 'Original source confirmed'];
        $this->post(route('admin.credit-claims.review', $claim), $decision)->assertForbidden();

        $this->actingAs($curator)->post(route('admin.shelves.store'), $shelf)->assertRedirect();
        $this->get(route('discover'))->assertInertia(fn ($page) => $page->component('Discovery/Discover')->missing('shelves'));
        $this->post(route('admin.credit-claims.review', $claim), $decision)->assertRedirect();
        $this->get(route('posts.show', $post))->assertInertia(fn ($page) => $page
            ->where('post.credited_artist.username', $claimant->username)->where('post.source_url', 'https://example.test/original'));
        $this->assertSame($curator->id, $post->fresh()->user_id);
    }
}
