<?php

namespace Tests\Feature;

use App\Models\Post;
use App\Models\Setting;
use App\Models\Tag;
use App\Models\User;
use App\Support\CollectionLibrary;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class TagMuteTest extends TestCase
{
    use RefreshDatabase;

    private function works(User $artist): array
    {
        $withTag = Post::create(['user_id' => $artist->id, 'type' => 'image', 'rating' => 'sfw', 'title' => 'Muted work', 'likes_count' => 50]);
        Tag::syncFor($withTag, ['watercolor']);
        $clean = Post::create(['user_id' => $artist->id, 'type' => 'image', 'rating' => 'sfw', 'title' => 'Clean work', 'likes_count' => 50]);
        Tag::syncFor($clean, ['sketch']);

        return [$withTag, $clean];
    }

    public function test_mutes_are_stored_on_the_account_not_the_session(): void
    {
        $user = User::factory()->create();

        $this->actingAs($user)->post(route('settings.muted-tags.store'), ['tag' => 'Watercolor'])->assertRedirect();

        $this->assertSame('["watercolor"]', Setting::where('user_id', $user->id)->where('key', 'muted_tags')->value('value'));
        // a brand new session still sees them
        $this->flushSession();
        $this->actingAs($user)->get(route('settings.edit'))
            ->assertInertia(fn ($page) => $page->where('mutedTags', ['watercolor']));
    }

    public function test_mutes_hide_works_in_the_gallery(): void
    {
        $artist = User::factory()->create();
        [$muted] = $this->works($artist);
        $viewer = User::factory()->create();
        CollectionLibrary::addMute($viewer, 'watercolor');

        $this->actingAs($viewer)->get('/?window=all')
            ->assertOk()
            ->assertInertia(fn ($page) => $page->has('posts', 1)
                ->where('posts.0.title', 'Clean work'));
    }

    public function test_mutes_hide_works_from_search(): void
    {
        $artist = User::factory()->create();
        $this->works($artist);
        $viewer = User::factory()->create();
        CollectionLibrary::addMute($viewer, 'watercolor');

        // browse is the search results page and applies the mutes
        $this->actingAs($viewer)->get('/?q=work&window=all')
            ->assertOk()
            ->assertInertia(fn ($page) => $page
                ->has('posts', 1)
                ->where('posts.0.title', 'Clean work'));
    }

    public function test_mutes_hide_works_from_the_following_feed(): void
    {
        $artist = User::factory()->create(['username' => 'followedartist']);
        $this->works($artist);
        $viewer = User::factory()->create();
        $viewer->following()->attach($artist->id);
        CollectionLibrary::addMute($viewer, 'watercolor');

        $this->actingAs($viewer)->get(route('following'))
            ->assertOk()
            ->assertInertia(fn ($page) => $page->has('posts', 1));
    }

    public function test_removing_a_mute_brings_works_back(): void
    {
        $artist = User::factory()->create();
        $this->works($artist);
        $viewer = User::factory()->create();
        CollectionLibrary::addMute($viewer, 'watercolor');

        $this->actingAs($viewer)->delete(route('settings.muted-tags.destroy'), ['tag' => 'watercolor'])->assertRedirect();
        $this->actingAs($viewer)->get('/?window=all')->assertInertia(fn ($page) => $page->has('posts', 2));
    }

    public function test_show_less_mutes_the_tags_of_a_suggestion(): void
    {
        $artist = User::factory()->create();
        [$muted] = $this->works($artist);
        $viewer = User::factory()->create();

        $this->actingAs($viewer)->post(route('discover.show-less'), ['post' => $muted->id])->assertRedirect();

        $this->assertContains('watercolor', CollectionLibrary::mutedFor($viewer->fresh()));
    }

    public function test_mutes_are_capped_and_cleaned(): void
    {
        $user = User::factory()->create();
        $names = array_map(fn ($i) => "Tag {$i}", range(1, 80));
        CollectionLibrary::setMutedTags(request()->setUserResolver(fn () => $user), implode(',', $names));

        $stored = CollectionLibrary::mutedFor($user);
        $this->assertLessThanOrEqual(60, count($stored));
        $this->assertSame('tag 1', $stored[0]);
    }

    public function test_mute_routes_require_an_account(): void
    {
        // these must sit behind auth: as a public route the handler silently no-ops
        $this->post(route('settings.muted-tags.store'), ['tag' => 'watercolor'])->assertRedirect(route('login'));
        $this->delete(route('settings.muted-tags.destroy'), ['tag' => 'watercolor'])->assertRedirect(route('login'));
        $this->post(route('discover.show-less'), ['post' => 1])->assertRedirect(route('login'));
    }

    public function test_guests_have_no_mutes_and_signed_out_feeds_are_unaffected(): void
    {
        $artist = User::factory()->create();
        $this->works($artist);

        $this->get('/?window=all')->assertOk()->assertInertia(fn ($page) => $page->has('posts', 2));
    }
}
