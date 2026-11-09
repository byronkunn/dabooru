<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ProfileConnectionsTest extends TestCase
{
    use RefreshDatabase;

    public function test_lists_followers_and_following_separately_with_public_fields(): void
    {
        $profile = User::factory()->create();
        $follower = User::factory()->create();
        $following = User::factory()->create();
        $profile->followers()->attach($follower);
        $profile->following()->attach($following);

        foreach (['followers' => $follower, 'following' => $following] as $type => $person) {
            $this->getJson(route('profile.connections', [$profile->username, $type]))
                ->assertOk()
                ->assertJsonCount(1, 'data')
                ->assertJsonPath('data.0.username', $person->username)
                ->assertJsonPath('data.0.url', '/u/'.$person->username)
                ->assertJsonPath('has_more', false)
                ->assertJsonMissingPath('data.0.email')
                ->assertJsonStructure(['data' => [['id', 'name', 'username', 'avatar', 'url']]]);
        }
    }

    public function test_paginates_accounts_and_excludes_unavailable_accounts(): void
    {
        $profile = User::factory()->create();
        $people = User::factory()->count(26)->create();
        $profile->followers()->attach($people);
        $profile->followers()->attach(User::factory()->create(['banned_at' => now()]));
        $profile->followers()->attach(User::factory()->unverified()->create());
        $profile->followers()->attach(User::factory()->create(['is_unsorted' => true]));
        $url = route('profile.connections', [$profile->username, 'followers']);

        $first = $this->getJson($url)->assertOk()->assertJsonCount(25, 'data')->assertJsonPath('has_more', true);
        $second = $this->getJson($url.'?page=2')->assertOk()->assertJsonCount(1, 'data')->assertJsonPath('has_more', false);
        $this->assertNotContains($second->json('data.0.id'), array_column($first->json('data'), 'id'));
    }

    public function test_empty_missing_and_invalid_lists(): void
    {
        $profile = User::factory()->create();
        $this->getJson(route('profile.connections', [$profile->username, 'following']))
            ->assertOk()->assertExactJson(['data' => [], 'has_more' => false]);
        $this->getJson('/u/missing-user/followers')->assertNotFound();
        $this->getJson('/u/'.$profile->username.'/password')->assertNotFound();
    }
}
