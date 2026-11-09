<?php

namespace Tests\Feature;

use App\Models\Post;
use App\Models\Tag;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ReaderSetupTest extends TestCase
{
    use RefreshDatabase;

    private function artistWithWork(): User
    {
        $artist = User::factory()->create(['username' => 'painter', 'is_unsorted' => false]);
        $post = Post::create(['user_id' => $artist->id, 'type' => 'image', 'rating' => 'sfw', 'title' => 'Work']);
        Tag::syncFor($post, ['watercolor']);

        return $artist;
    }

    public function test_a_new_account_is_sent_to_setup_after_registering(): void
    {
        $this->post('/register', [
            'name' => 'New Reader',
            'username' => 'newreader',
            'email' => 'new@example.test',
            'password' => 'password123',
            'password_confirmation' => 'password123',
            'date_of_birth' => now()->subYears(25)->toDateString(),
            'age_confirmed' => 'on',
        ])->assertRedirect(route('setup.show'));

        $user = User::where('username', 'newreader')->firstOrFail();
        $this->assertNull($user->setup_completed_at);
        $this->actingAs($user)->get(route('setup.show'))
            ->assertOk()
            ->assertInertia(fn ($page) => $page->component('Setup/Index')->has('suggestedTags')->has('suggestedArtists'));
    }

    public function test_finishing_setup_follows_tags_and_artists_and_sets_the_rating_preference(): void
    {
        $artist = $this->artistWithWork();
        $reader = User::factory()->create(['setup_completed_at' => null, 'show_explicit' => true]);

        $this->actingAs($reader)->post(route('setup.store'), [
            'tags' => ['watercolor'],
            'artists' => ['painter'],
            'show_explicit' => false,
        ])->assertRedirect(route('gallery'));

        $reader->refresh();
        $this->assertNotNull($reader->setup_completed_at);
        $this->assertFalse((bool) $reader->show_explicit);
        $this->assertSame([$artist->id], $reader->following()->pluck('users.id')->all());
        $this->assertSame(['watercolor'], $reader->followedTags()->pluck('name')->all());
    }

    public function test_skipping_marks_setup_done_without_following_anything(): void
    {
        $this->artistWithWork();
        $reader = User::factory()->create(['setup_completed_at' => null]);

        $this->actingAs($reader)->post(route('setup.skip'))->assertRedirect(route('gallery'));

        $reader->refresh();
        $this->assertNotNull($reader->setup_completed_at);
        $this->assertSame(0, $reader->following()->count());
    }

    public function test_setup_is_not_shown_twice(): void
    {
        $reader = User::factory()->create(['setup_completed_at' => now()]);

        $this->actingAs($reader)->get(route('setup.show'))->assertRedirect(route('gallery'));
    }

    public function test_guests_cannot_reach_setup(): void
    {
        $this->get(route('setup.show'))->assertRedirect(route('login'));
        $this->post(route('setup.store'))->assertRedirect(route('login'));
    }

    public function test_a_reader_cannot_follow_themselves(): void
    {
        $this->artistWithWork();
        $reader = User::factory()->create(['setup_completed_at' => null, 'username' => 'selfreader']);

        $this->actingAs($reader)->post(route('setup.store'), ['artists' => ['selfreader']])->assertRedirect();

        $this->assertSame(0, $reader->following()->count());
    }
}
