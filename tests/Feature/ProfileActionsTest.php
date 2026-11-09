<?php

namespace Tests\Feature;

use App\Models\AppNotification;
use App\Models\Block;
use App\Models\Conversation;
use App\Models\Follow;
use App\Models\Setting;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ProfileActionsTest extends TestCase
{
    use RefreshDatabase;

    public function test_follow_is_idempotent_and_profile_relationship_and_counts_refresh(): void
    {
        $viewer = User::factory()->create();
        $profile = User::factory()->create();
        $url = route('users.follow', $profile);
        $this->actingAs($viewer)->post($url)->assertSessionHasNoErrors();
        $this->post($url)->assertSessionHasNoErrors();
        $this->assertDatabaseCount('follows', 1);
        $this->assertSame(1, AppNotification::where('type', 'follow')->count());
        $this->get(route('profile.show', $profile->username))->assertInertia(fn ($page) => $page
            ->where('isFollowing', true)->where('profile.followers', 1)
            ->where('relationship.canFollow', true)->where('relationship.canMessage', true));
        $this->delete(route('users.unfollow', $profile))->assertRedirect();
        $this->get(route('profile.show', $profile->username))->assertInertia(fn ($page) => $page
            ->where('isFollowing', false)->where('profile.followers', 0));
    }

    public function test_block_prevents_following_in_both_directions_and_unblock_does_not_restore_follows(): void
    {
        $viewer = User::factory()->create();
        $profile = User::factory()->create();
        Follow::create(['follower_id' => $viewer->id, 'following_id' => $profile->id]);
        Follow::create(['follower_id' => $profile->id, 'following_id' => $viewer->id]);
        $this->actingAs($viewer)->post(route('users.block', $profile))->assertRedirect();
        $this->get(route('profile.show', $profile->username))->assertInertia(fn ($page) => $page
            ->where('relationship.blocked', true)->where('relationship.canFollow', false)
            ->where('relationship.canMessage', false)->where('isFollowing', false));
        $this->post(route('users.follow', $profile))->assertSessionHasErrors('follow');
        $this->actingAs($profile)->post(route('users.follow', $viewer))->assertSessionHasErrors('follow');
        $this->get(route('profile.show', $viewer->username))->assertInertia(fn ($page) => $page
            ->where('relationship.blockedBy', true)->where('relationship.canFollow', false)
            ->where('relationship.canMessage', false));
        $this->assertDatabaseCount('follows', 0);
        $this->actingAs($viewer)->delete(route('users.unblock', $profile))->assertRedirect();
        $this->assertDatabaseCount('follows', 0);
        $this->post(route('users.follow', $profile))->assertSessionHasNoErrors();
        $this->assertDatabaseCount('follows', 1);
    }

    public function test_message_button_respects_recipient_privacy_and_existing_conversations(): void
    {
        $viewer = User::factory()->create();
        $profile = User::factory()->create();
        $setting = Setting::create(['user_id' => $profile->id, 'key' => 'chat_dm_permission', 'value' => 'following']);
        $url = route('profile.show', $profile->username);
        $this->actingAs($viewer)->get($url)->assertInertia(fn ($page) => $page->where('relationship.canMessage', false));
        $profile->following()->attach($viewer);
        $this->get($url)->assertInertia(fn ($page) => $page
            ->where('relationship.canMessage', true)->where('relationship.followsYou', true));
        $setting->update(['value' => 'nobody']);
        $this->get($url)->assertInertia(fn ($page) => $page->where('relationship.canMessage', false));
        $chat = Conversation::between($viewer->id, $profile->id);
        $chat->update(['request_status' => 'accepted']);
        $this->get($url)->assertInertia(fn ($page) => $page->where('relationship.canMessage', true));
        Block::create(['blocker_id' => $viewer->id, 'blocked_id' => $profile->id]);
        $this->get($url)->assertInertia(fn ($page) => $page->where('relationship.canMessage', false));
    }

    public function test_cannot_follow_self_or_a_banned_account(): void
    {
        $viewer = User::factory()->create();
        $banned = User::factory()->create(['banned_at' => now()]);
        $this->actingAs($viewer)->post(route('users.follow', $viewer))->assertStatus(422);
        $this->post(route('users.follow', $banned))->assertSessionHasErrors('follow');
        $this->assertDatabaseCount('follows', 0);
    }
}
