<?php

namespace Tests\Feature;

use App\Models\Block;
use App\Models\Bookmark;
use App\Models\Post;
use App\Models\SavedCollection;
use App\Models\Tag;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class CollectionsTest extends TestCase
{
    use RefreshDatabase;

    private function work(User $author, string $type = 'image', string $rating = 'sfw'): Post
    {
        return Post::create(['user_id' => $author->id, 'type' => $type, 'rating' => $rating, 'title' => ucfirst($type).' work']);
    }

    private function folder(User $user, string $name): SavedCollection
    {
        return SavedCollection::create(['user_id' => $user->id, 'name' => $name]);
    }

    public function test_picker_saves_to_multiple_private_collections_and_can_create_one(): void
    {
        $reader = User::factory()->create();
        $folder = $this->folder($reader, 'Reading');
        $other = $this->folder(User::factory()->create(), 'Not mine');
        $post = $this->work($reader);
        $this->actingAs($reader)->getJson(route('bookmarks.collections', $post))
            ->assertJsonCount(1, 'collections')->assertJsonPath('selected', []);
        $this->patch(route('bookmarks.collections.update', $post), [
            'collections' => [$folder->id], 'new_name' => 'Favorites',
        ])->assertSessionHasNoErrors();
        $new = SavedCollection::where('name', 'Favorites')->firstOrFail();
        $this->assertNull($new->share_token);
        $this->assertDatabaseCount('bookmarks', 1);
        $this->assertDatabaseCount('bookmark_saved_collection', 2);
        $this->getJson(route('bookmarks.collections', $post))->assertJsonCount(2, 'selected');
        $this->patch(route('bookmarks.collections.update', $post), ['collections' => [$other->id], 'new_name' => 'Must not create'])
            ->assertSessionHasErrors('collections');
        $this->assertDatabaseMissing('saved_collections', ['name' => 'Must not create']);
        $this->assertDatabaseCount('bookmark_saved_collection', 2);
        $this->patch(route('bookmarks.collections.update', $post), ['collections' => [$new->id]])->assertSessionHasNoErrors();
        $this->assertDatabaseCount('bookmark_saved_collection', 1);
        $this->assertDatabaseHas('bookmark_saved_collection', ['saved_collection_id' => $new->id]);
        $this->patch(route('bookmarks.collections.update', $post), ['collections' => []])->assertSessionHasNoErrors();
        $this->assertDatabaseCount('bookmarks', 1);
        $this->assertDatabaseCount('bookmark_saved_collection', 0);
    }

    public function test_recently_added_is_membership_order_not_work_publication_order(): void
    {
        $user = User::factory()->create();
        $folder = $this->folder($user, 'Order');
        $olderPost = $this->work($user);
        $newerPost = $this->work($user, 'video');
        $this->actingAs($user)->freezeTime();
        $this->post(route('collections.posts.add', [$folder, $newerPost]));
        $this->post(route('collections.posts.add', [$folder, $olderPost]));
        $this->get(route('collections.show', $folder))->assertInertia(fn ($page) => $page
            ->where('order', 'recent')->where('posts.0.id', $olderPost->id)->where('collection.count', 2));
        $this->patch(route('collections.reorder', $folder), ['order' => [$olderPost->id, $newerPost->id]])->assertSessionHasNoErrors();
        $this->get(route('collections.show', ['collection' => $folder, 'order' => 'custom']))->assertInertia(fn ($page) => $page
            ->where('posts.0.id', $olderPost->id)->where('posts.1.id', $newerPost->id));
        $this->get(route('collections.show', $folder))->assertInertia(fn ($page) => $page->where('posts.0.id', $olderPost->id));
        $this->patch(route('collections.reorder', $folder), ['order' => [$newerPost->id]])->assertSessionHasErrors('order');
        $this->patch(route('collections.reorder', $folder), ['order' => [$newerPost->id, $newerPost->id]])->assertSessionHasErrors('order.0');
        $this->get(route('collections.show', ['collection' => $folder, 'order' => 'custom']))->assertInertia(fn ($page) => $page->where('posts.0.id', $olderPost->id));
        $this->get(route('collections.index', ['view' => 'all']))->assertInertia(fn ($page) => $page->where('posts.0.id', $olderPost->id)->where('showAll', true));
    }

    public function test_picker_rejects_an_existing_name_in_the_new_collection_field(): void
    {
        $owner = User::factory()->create();
        $public = $this->folder($owner, 'References');
        $public->update(['is_public' => true]);
        $post = $this->work($owner);

        $this->actingAs($owner)->patch(route('bookmarks.collections.update', $post), [
            'collections' => [], 'new_name' => 'References',
        ])->assertSessionHasErrors('new_name');
        $this->assertDatabaseCount('bookmarks', 0);
        $this->assertDatabaseCount('bookmark_saved_collection', 0);
    }

    public function test_detail_omits_other_folders_and_protects_browser_history(): void
    {
        $owner = User::factory()->create();
        $folder = $this->folder($owner, 'Selected');
        $this->folder($owner, 'Unrelated');

        $response = $this->actingAs($owner)->get(route('collections.show', $folder))->assertInertia(fn ($page) => $page
            ->where('collection.name', 'Selected')->missing('collections')->where('allSaved', null));
        $this->assertTrue($response->viewData('page')['encryptHistory']);
        $this->assertStringContainsString('no-store', $response->headers->get('Cache-Control'));

        $this->get(route('collections.index'))->assertInertia(fn ($page) => $page->has('collections', 2)->missing('posts'));
    }

    public function test_editing_description_and_cover_is_owner_only_and_cover_must_be_a_member(): void
    {
        $user = User::factory()->create();
        $folder = $this->folder($user, 'Favorites');
        $post = $this->work($user);
        $outside = $this->work($user);
        $this->actingAs($user)->post(route('collections.posts.add', [$folder, $post]));
        $this->patch(route('collections.update', $folder), ['name' => 'Showcase', 'description' => 'My reading list', 'cover_post_id' => $post->id])
            ->assertSessionHasNoErrors();
        $this->get(route('collections.show', $folder))->assertInertia(fn ($page) => $page
            ->where('collection.name', 'Showcase')->where('collection.description', 'My reading list')->where('collection.cover.id', $post->id));
        $this->patch(route('collections.update', $folder), ['name' => 'Bad cover', 'cover_post_id' => $outside->id])->assertSessionHasErrors('cover_post_id');
        $this->assertSame('Showcase', $folder->fresh()->name);
        $this->actingAs(User::factory()->create())->patch(route('collections.update', $folder), ['name' => 'Stolen'])->assertNotFound();
        $this->patch(route('collections.reorder', $folder), ['order' => [$post->id]])->assertNotFound();
        $this->actingAs($user)->patch(route('bookmarks.collections.update', $post), ['collections' => []])
            ->assertSessionHasNoErrors();
        $this->assertNull($folder->fresh()->cover_post_id);
        $this->assertDatabaseCount('bookmarks', 1);
        $this->delete(route('collections.destroy', $folder));
        $this->assertDatabaseCount('bookmarks', 1);
    }

    public function test_unsaving_removes_every_membership_and_undo_restores_them_once(): void
    {
        $user = User::factory()->create();
        $one = $this->folder($user, 'One');
        $two = $this->folder($user, 'Two');
        $post = $this->work($user);
        $this->actingAs($user)->post(route('collections.posts.add', [$one, $post]));
        $this->post(route('collections.posts.add', [$two, $post]));
        $one->update(['cover_post_id' => $post->id]);
        $bookmark = Bookmark::firstOrFail();
        $created = $bookmark->created_at;
        $added = DB::table('bookmark_saved_collection')->where('saved_collection_id', $one->id)->value('added_at');
        $this->delete(route('posts.unsave', $post))->assertSessionHas('bookmarkUndo');
        $token = session('bookmark_undo.token');
        $this->assertDatabaseCount('bookmarks', 0);
        $this->assertDatabaseCount('bookmark_saved_collection', 0);
        $this->assertNull($one->fresh()->cover_post_id);
        $this->post(route('bookmarks.undo'), ['token' => $token])->assertSessionHasNoErrors();
        $this->assertDatabaseCount('bookmarks', 1);
        $this->assertDatabaseCount('bookmark_saved_collection', 2);
        $this->assertSame($post->id, $one->fresh()->cover_post_id);
        $this->assertEquals($created, Bookmark::firstOrFail()->created_at);
        $this->assertSame($added, DB::table('bookmark_saved_collection')->where('saved_collection_id', $one->id)->value('added_at'));
        $this->post(route('bookmarks.undo'), ['token' => $token])->assertSessionHasErrors('token');
        $this->assertDatabaseCount('bookmark_saved_collection', 2);
    }

    public function test_undo_handles_deleted_collections_new_memberships_expiry_and_other_users(): void
    {
        $user = User::factory()->create();
        $folder = $this->folder($user, 'Reading');
        $deleted = $this->folder($user, 'Deleted');
        $first = $this->work($user);
        $last = $this->work($user, 'video');
        $this->actingAs($user)->post(route('collections.posts.add', [$folder, $first]));
        $this->post(route('collections.posts.add', [$folder, $last]));
        $this->post(route('collections.posts.add', [$deleted, $last]));
        $this->delete(route('posts.unsave', $last));
        $token = session('bookmark_undo.token');
        $this->delete(route('collections.destroy', $deleted));
        $new = $this->work($user, 'album');
        $this->post(route('collections.posts.add', [$folder, $new]));
        $this->actingAs(User::factory()->create())->post(route('bookmarks.undo'), ['token' => $token])->assertSessionHasErrors('token');
        $this->actingAs($user)->post(route('bookmarks.undo'), ['token' => $token])->assertSessionHasNoErrors();
        $this->assertDatabaseCount('bookmark_saved_collection', 3);
        $this->assertSame(3, DB::table('bookmark_saved_collection')->where('saved_collection_id', $folder->id)->distinct()->count('position'));
        $this->get(route('collections.show', ['collection' => $folder, 'order' => 'custom']))
            ->assertInertia(fn ($page) => $page->where('posts.0.id', $first->id)->where('posts.1.id', $last->id)->where('posts.2.id', $new->id));
        $this->delete(route('posts.unsave', $last));
        $token = session('bookmark_undo.token');
        $this->travel(6)->minutes();
        $this->post(route('bookmarks.undo'), ['token' => $token])->assertSessionHasErrors('token');
        $this->assertDatabaseCount('bookmarks', 2);
    }

    public function test_undo_preserves_recent_order_when_save_times_are_identical(): void
    {
        $user = User::factory()->create();
        $folder = $this->folder($user, 'Same-second saves');
        $first = $this->work($user);
        $second = $this->work($user);
        $this->actingAs($user);
        $this->freezeTime();
        foreach ([$first, $second] as $post) {
            $this->post(route('collections.posts.add', [$folder, $post]));
        }
        $this->delete(route('posts.unsave', $first));
        $this->post(route('bookmarks.undo'), ['token' => session('bookmark_undo.token')])->assertSessionHasNoErrors();
        foreach ([route('collections.show', $folder), route('collections.index', ['view' => 'all'])] as $url) {
            $this->get($url)->assertInertia(fn ($page) => $page->where('posts.0.id', $second->id)->where('posts.1.id', $first->id));
        }
    }

    public function test_viewer_rating_blocks_and_muted_tags_control_cards_covers_and_counts(): void
    {
        $owner = User::factory()->create(['show_explicit' => true]);
        $reader = User::factory()->create(['show_explicit' => false]);
        $blockedAuthor = User::factory()->create();
        $folder = $this->folder($owner, 'Shared shelf');
        $visible = $this->work($owner);
        $explicit = $this->work($owner, 'album', 'explicit');
        $muted = $this->work($owner, 'video');
        $blocked = $this->work($blockedAuthor);
        $draft = Post::create(['user_id' => $owner->id, 'type' => 'image', 'rating' => 'sfw', 'is_draft' => true]);
        Tag::syncFor($muted, ['spoilers']);
        $this->actingAs($owner);
        foreach ([$visible, $explicit, $muted, $blocked] as $post) {
            $this->post(route('collections.posts.add', [$folder, $post]));
        }
        $this->post(route('collections.posts.add', [$folder, $draft]))->assertNotFound();
        $this->patch(route('collections.update', $folder), ['name' => $folder->name, 'cover_post_id' => $explicit->id]);
        $this->patch(route('collections.share', $folder), ['shared' => true]);
        $url = route('collections.shared', $folder->fresh()->share_token);
        Block::create(['blocker_id' => $reader->id, 'blocked_id' => $blockedAuthor->id]);
        $this->actingAs($reader)->get($url.'?exclude_tags=spoilers')->assertInertia(fn ($page) => $page
            ->has('posts', 2)->where('mutedTags', []));
        $this->from($url.'?order=custom&page=2')->patch(route('library.muted-tags.update'), ['exclude_tags' => 'spoilers'])
            ->assertRedirect($url.'?order=custom');
        $this->get($url)->assertInertia(fn ($page) => $page
            ->has('posts', 1)->where('posts.0.id', $visible->id)->where('collection.count', 1)
            ->where('collection.cover.id', $visible->id)->where('collection.cover_post_id', null)->where('ownerUrl', null));
        $this->get($url.'?exclude_tags=')->assertInertia(fn ($page) => $page->has('posts', 1)->where('mutedTags', ['spoilers']));
        $this->from($url)->patch(route('library.muted-tags.update'), ['exclude_tags' => ''])->assertRedirect($url);
        $this->get($url)->assertInertia(fn ($page) => $page->has('posts', 2));
        $reader->update(['show_explicit' => true]);
        $this->get($url)->assertInertia(fn ($page) => $page->has('posts', 3)->where('collection.cover.id', $explicit->id));
        $this->actingAs($owner)->get(route('collections.index'))->assertInertia(fn ($page) => $page
            ->where('allSaved.count', 4)->where('collections.0.count', 4));
        $this->get($url)->assertInertia(fn ($page) => $page->where('ownerUrl', route('collections.show', ['collection' => $folder, 'edit' => 1])));
        Block::create(['blocker_id' => $reader->id, 'blocked_id' => $owner->id]);
        $this->actingAs($reader)->get($url)->assertNotFound();
    }

    public function test_public_collections_appear_on_profiles_and_have_viewer_filtered_public_pages(): void
    {
        $owner = User::factory()->create(['show_explicit' => true]);
        $viewer = User::factory()->create();
        $public = $this->folder($owner, 'Public shelf');
        $private = $this->folder($owner, 'Private shelf');
        $visible = $this->work($owner);
        $explicit = $this->work($owner, 'album', 'explicit');
        $this->actingAs($owner);
        foreach ([$visible, $explicit] as $post) {
            $this->post(route('collections.posts.add', [$public, $post]));
            $this->post(route('collections.posts.add', [$private, $post]));
        }
        $this->patch(route('collections.visibility', $public), ['is_public' => true])->assertSessionHasNoErrors();
        $this->assertTrue($public->fresh()->is_public);
        $this->assertDatabaseHas('saved_collections', ['id' => $public->id, 'share_token' => null]);

        $this->get(route('profile.show', ['username' => $owner->username, 'tab' => 'collections']))
            ->assertInertia(fn ($page) => $page->where('tab', 'collections')
                ->has('collections', 1)->where('collections.0.name', 'Public shelf')->where('collections.0.count', 2));
        $publicUrl = route('collections.public', [$owner->username, $public]);
        $this->actingAs($viewer)->get($publicUrl)
            ->assertInertia(fn ($page) => $page->where('name', 'Public shelf')->where('collection.isPublic', true)
                ->where('collection.count', 1)->has('posts', 1)->where('posts.0.id', $visible->id)
                ->where('ownerUrl', null));
        $this->actingAs($owner)->get($publicUrl)
            ->assertInertia(fn ($page) => $page->where('ownerUrl', route('collections.show', ['collection' => $public, 'edit' => 1])));

        $this->actingAs($owner)->patch(route('collections.share', $public), ['shared' => true])->assertSessionHasNoErrors();
        $shareUrl = route('collections.shared', $public->fresh()->share_token);
        $this->actingAs($viewer)->get($shareUrl)->assertOk();
        $this->actingAs($owner)->patch(route('collections.visibility', $public), ['is_public' => false])
            ->assertSessionHasNoErrors();
        $this->assertDatabaseHas('saved_collections', ['id' => $public->id, 'is_public' => false, 'share_token' => null]);
        $this->actingAs($viewer)->get($publicUrl)->assertNotFound();
        $this->get($shareUrl)->assertNotFound();
        $this->get(route('profile.show', ['username' => $owner->username, 'tab' => 'collections']))
            ->assertInertia(fn ($page) => $page->has('collections', 0));
    }

    public function test_public_collection_pages_obey_blocks_and_owner_boundaries(): void
    {
        $owner = User::factory()->create();
        $viewer = User::factory()->create();
        $other = User::factory()->create();
        $public = $this->folder($owner, 'Public shelf');
        $this->actingAs($owner)->patch(route('collections.visibility', $public), ['is_public' => true]);
        $url = route('collections.public', [$owner->username, $public]);

        $this->get(route('collections.public', [$other->username, $public]))->assertNotFound();
        Block::create(['blocker_id' => $viewer->id, 'blocked_id' => $owner->id]);
        $this->actingAs($viewer)->get($url)->assertNotFound();
        $this->actingAs($owner)->get(route('profile.show', ['username' => $viewer->username, 'tab' => 'collections']))
            ->assertInertia(fn ($page) => $page->has('collections', 0));
    }

    public function test_guests_can_view_public_collections_without_link_sharing(): void
    {
        $owner = User::factory()->create();
        $collection = $this->folder($owner, 'Public shelf');
        $collection->update(['is_public' => true]);
        $post = $this->work($owner);
        $this->actingAs($owner)->post(route('collections.posts.add', [$collection, $post]));
        auth()->logout();

        $this->get(route('collections.public', [$owner->username, $collection]))
            ->assertInertia(fn ($page) => $page->where('collection.isPublic', true)->where('collection.count', 1)
                ->where('posts.0.id', $post->id)->where('ownerUrl', null));
        $this->assertNull($collection->fresh()->share_token);
    }

    public function test_custom_reorder_keeps_hidden_slots_and_editing_includes_all_pages(): void
    {
        $user = User::factory()->create(['show_explicit' => true]);
        $folder = $this->folder($user, 'Long list');
        $this->actingAs($user);
        $ids = [];
        for ($i = 0; $i < 40; $i++) {
            $post = $this->work($user, 'image', $i === 1 ? 'explicit' : 'sfw');
            $ids[] = $post->id;
            $this->post(route('collections.posts.add', [$folder, $post]));
        }
        $user->update(['show_explicit' => false]);
        $this->get(route('collections.show', ['collection' => $folder, 'edit' => 1]))->assertInertia(fn ($page) => $page
            ->has('posts', 36)->has('editItems', 39)->where('collection.count', 39)->where('editing', true));
        $visible = array_values(array_filter($ids, fn ($id) => $id !== $ids[1]));
        $this->patch(route('collections.reorder', $folder), ['order' => array_reverse($visible)])->assertSessionHasNoErrors();
        $hidden = Bookmark::where('post_id', $ids[1])->firstOrFail();
        $this->assertSame(2, DB::table('bookmark_saved_collection')->where('bookmark_id', $hidden->id)->value('position'));
        $this->get(route('collections.show', ['collection' => $folder, 'order' => 'custom', 'page' => 2]))->assertInertia(fn ($page) => $page->has('posts', 3));
    }

    public function test_owner_can_remove_a_hidden_work_without_revealing_its_details(): void
    {
        $owner = User::factory()->create(['show_explicit' => true]);
        $viewer = User::factory()->create(['show_explicit' => true]);
        $folder = $this->folder($owner, 'Public shelf');
        $folder->update(['is_public' => true]);
        $post = $this->work($owner, 'image', 'explicit');
        $this->actingAs($owner)->post(route('collections.posts.add', [$folder, $post]));
        $folder->update(['cover_post_id' => $post->id]);
        $bookmark = Bookmark::firstOrFail();
        $owner->update(['show_explicit' => false]);

        $this->get(route('collections.show', ['collection' => $folder, 'edit' => 1]))->assertInertia(fn ($page) => $page
            ->has('editItems', 0)->where('hiddenBookmarks', [$bookmark->id])->where('collection.cover', null));
        $url = route('collections.public', [$owner->username, $folder]);
        $remove = route('collections.bookmarks.remove', [$folder, $bookmark]);
        $this->actingAs($viewer)->get($url)->assertInertia(fn ($page) => $page->has('posts', 1));
        $this->delete($remove)->assertNotFound();

        $this->actingAs($owner)->delete($remove)->assertSessionHasNoErrors();
        $this->assertDatabaseHas('bookmarks', ['id' => $bookmark->id]);
        $this->assertNull($folder->fresh()->cover_post_id);
        $this->actingAs($viewer)->get($url)->assertInertia(fn ($page) => $page->has('posts', 0));
    }
}
