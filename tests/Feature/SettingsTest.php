<?php

namespace Tests\Feature;

use App\Models\AppNotification;
use App\Models\Comment;
use App\Models\Post;
use App\Models\Setting;
use App\Models\User;
use Illuminate\Auth\Notifications\VerifyEmail;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class SettingsTest extends TestCase
{
    use RefreshDatabase;

    public function test_settings_show_the_actual_content_preference_and_save_only_valid_user_scoped_values(): void
    {
        $user = User::factory()->create(['show_explicit' => false]);
        $other = User::factory()->create();
        Setting::create(['user_id' => $user->id, 'key' => 'show_explicit', 'value' => '1']);
        $this->actingAs($user)->get(route('settings.edit'))->assertInertia(fn ($page) => $page
            ->component('Settings/Index')->where('settings.show_explicit', false)
            ->where('settings.notification_likes', true)
            ->where('messagingPrivacy.chat_read_receipts', false));
        $this->post(route('settings.update'), ['settings' => ['show_explicit' => '0', 'notification_likes' => false], 'user_id' => $other->id])
            ->assertSessionHasNoErrors();
        $this->assertFalse($user->fresh()->show_explicit);
        $this->assertFalse(Setting::enabled($user->id, 'notification_likes'));
        $this->assertTrue(Setting::enabled($other->id, 'notification_likes'));
        $this->post(route('settings.update'), ['settings' => ['notification_comments' => false]])->assertSessionHasNoErrors();
        $this->assertFalse(Setting::enabled($user->id, 'notification_likes'));
        foreach ([['show_explicit' => 'false'], ['notification_likes' => true, 'not_a_setting' => 1], []] as $settings) {
            $this->post(route('settings.update'), ['settings' => $settings])->assertSessionHasErrors();
        }
        $this->assertFalse(Setting::enabled($user->id, 'notification_likes'));
        $this->post(route('settings.update'), ['settings' => ['show_explicit' => true]])->assertSessionHasNoErrors();
        $this->assertTrue($user->fresh()->show_explicit);
    }

    public function test_profile_fields_and_image_removal_persist_and_unsafe_urls_are_rejected(): void
    {
        $user = User::factory()->create(['avatar_path' => '/old-avatar.png', 'banner_path' => '/old-banner.png']);
        $input = ['name' => 'Updated name', 'username' => 'Updated_Handle', 'bio' => 'Updated biography.',
            'location' => 'A studio', 'website' => 'https://example.com/portfolio', 'remove_avatar' => true, 'remove_banner' => true];
        $this->actingAs($user)->post(route('settings.profile'), $input)->assertSessionHasNoErrors();
        $user->refresh();
        $this->assertSame('updated_handle', $user->username);
        $this->assertSame('Updated biography.', $user->bio);
        $this->assertNull($user->avatar_path);
        $this->assertNull($user->banner_path);
        $this->get(route('settings.edit'))->assertInertia(fn ($page) => $page->where('user.website', $input['website']));
        $this->post(route('settings.profile'), [...$input, 'website' => 'javascript:alert(1)'])->assertSessionHasErrors('website');
        $this->assertSame($input['website'], $user->fresh()->website);
        $this->post(route('settings.profile'), [...$input, 'location' => str_repeat('x', 81)])->assertSessionHasErrors('location');
        $taken = User::factory()->create();
        $this->post(route('settings.profile'), [...$input, 'username' => $taken->username])->assertSessionHasErrors('username');
    }

    public function test_email_changes_require_password_unique_address_and_reverification(): void
    {
        Notification::fake();
        $user = User::factory()->create(['password' => Hash::make('original-password')]);
        $other = User::factory()->create();
        $this->actingAs($user)->put(route('settings.email'), ['email' => 'new@example.com', 'current_password' => 'wrong'])
            ->assertSessionHasErrors('current_password');
        $this->put(route('settings.email'), ['email' => $other->email, 'current_password' => 'original-password'])
            ->assertSessionHasErrors('email');
        $this->put(route('settings.email'), ['email' => 'new@example.com', 'current_password' => 'original-password'])
            ->assertSessionHasNoErrors();
        $this->assertSame('new@example.com', $user->fresh()->email);
        $this->assertNull($user->fresh()->email_verified_at);
        Notification::assertSentTo($user, VerifyEmail::class);
        $this->post(route('verification.send'))->assertSessionHas('status', 'verification-link-sent');
    }

    public function test_password_changes_validate_current_password_and_confirmation(): void
    {
        $user = User::factory()->create(['password' => Hash::make('original-password')]);
        $this->actingAs($user)->put(route('password.update'), [
            'current_password' => 'wrong', 'password' => 'new-password', 'password_confirmation' => 'new-password',
        ])->assertSessionHasErrors('current_password');
        $this->put(route('password.update'), [
            'current_password' => 'original-password', 'password' => 'new-password', 'password_confirmation' => 'different',
        ])->assertSessionHasErrors('password');
        $this->put(route('password.update'), [
            'current_password' => 'original-password', 'password' => 'new-password', 'password_confirmation' => 'new-password',
        ])->assertSessionHasNoErrors();
        $this->assertTrue(Hash::check('new-password', $user->fresh()->password));
    }

    public function test_notification_settings_control_future_activity(): void
    {
        $owner = User::factory()->create();
        $actor = User::factory()->create();
        $post = Post::create(['user_id' => $owner->id, 'type' => 'image', 'rating' => 'sfw']);
        $this->actingAs($owner)->post(route('settings.update'), ['settings' => [
            'notification_likes' => false, 'notification_comments' => false, 'notification_follows' => false,
        ]])->assertSessionHasNoErrors();
        $this->actingAs($actor)->post(route('posts.like', $post))->assertSessionHasNoErrors();
        $this->post(route('comments.store', $post), ['body' => 'A comment.'])->assertSessionHasNoErrors();
        $this->post(route('users.follow', $owner->username))->assertSessionHasNoErrors();
        $this->assertSame(0, AppNotification::where('user_id', $owner->id)->count());
        $this->actingAs($owner)->post(route('settings.update'), ['settings' => ['notification_comments' => true]])->assertSessionHasNoErrors();
        $this->actingAs($actor)->post(route('comments.store', $post), ['body' => 'Another comment.'])->assertSessionHasNoErrors();
        $this->assertSame(1, AppNotification::where('user_id', $owner->id)->where('type', 'comment')->count());
    }

    public function test_account_deletion_removes_published_draft_and_archived_works_and_updates_discussion_totals(): void
    {
        $user = User::factory()->create(['password' => Hash::make('original-password')]);
        $other = User::factory()->create();
        $published = Post::create(['user_id' => $user->id, 'type' => 'image', 'rating' => 'sfw']);
        $draft = Post::create(['user_id' => $user->id, 'type' => 'video', 'rating' => 'sfw', 'is_draft' => true]);
        $archived = Post::create(['user_id' => $user->id, 'type' => 'album', 'rating' => 'sfw']);
        $archived->delete();
        $remaining = Post::create(['user_id' => $other->id, 'type' => 'image', 'rating' => 'sfw']);
        Comment::create(['post_id' => $remaining->id, 'user_id' => $user->id, 'body' => 'Remove my comment.']);
        Comment::create(['post_id' => $remaining->id, 'user_id' => $other->id, 'body' => 'Keep my comment.']);
        $remaining->forceFill(['comments_count' => 2])->save();
        $this->actingAs($user)->post(route('posts.like', $remaining))->assertSessionHasNoErrors();
        $this->assertSame(1, $remaining->fresh()->likes_count);
        $this->actingAs($user)->delete(route('settings.account.destroy'), ['password' => 'original-password'])->assertRedirect(route('gallery'));
        foreach ([$published, $draft, $archived] as $post) {
            $this->assertDatabaseMissing('posts', ['id' => $post->id]);
        }
        $this->assertSame(1, $remaining->fresh()->comments_count);
        $this->assertSame(0, $remaining->fresh()->likes_count);
        $this->assertDatabaseHas('users', ['id' => $other->id]);
    }

    public function test_profile_image_upload_updates_only_the_signed_in_users_selected_image(): void
    {
        Storage::fake('public');
        config(['filesystems.upload_disk' => 'public']);
        $user = User::factory()->create(['bio' => 'Keep my bio']);
        $other = User::factory()->create();

        foreach (['avatar', 'banner'] as $type) {
            $this->actingAs($user)->from(route('profile.show', $user->username))
                ->post(route('settings.profile.image', $type), [
                    $type => UploadedFile::fake()->image('photo.png'),
                    'user_id' => $other->id,
                    'name' => 'Do not change',
                ])->assertRedirect(route('profile.show', $user->username))->assertSessionHasNoErrors();
            $user->refresh();
            Storage::disk('public')->assertExists($user->{$type.'_path'});
            $this->assertNull($other->fresh()->{$type.'_path'});
        }
        $this->assertSame('Keep my bio', $user->bio);
        $this->assertNotSame('Do not change', $user->name);
    }

    public function test_profile_image_upload_requires_a_valid_image_within_its_size_limit(): void
    {
        Storage::fake('public');
        config(['filesystems.upload_disk' => 'public']);
        $user = User::factory()->create();
        foreach (['avatar' => 5120, 'banner' => 10240] as $type => $limit) {
            $url = route('settings.profile.image', $type);
            $this->actingAs($user)->post($url)->assertSessionHasErrors($type);
            $this->post($url, [$type => UploadedFile::fake()->create('file.txt')])->assertSessionHasErrors($type);
            $this->post($url, [$type => UploadedFile::fake()->image('large.jpg')->size($limit + 1)])->assertSessionHasErrors($type);
            $this->post($url, [$type => UploadedFile::fake()->image('limit.jpg')->size($limit)])->assertSessionHasNoErrors();
        }
    }

    public function test_guests_cannot_upload_profile_images_and_invalid_targets_are_rejected(): void
    {
        $this->post(route('settings.profile.image', 'avatar'))->assertRedirect(route('login'));
        $this->actingAs(User::factory()->create())->post('/settings/profile/image/password')->assertNotFound();
    }

    public function test_user_can_delete_their_account_with_correct_password(): void
    {
        $user = User::factory()->create([
            'password' => Hash::make('secret-password-123'),
        ]);

        $response = $this->actingAs($user)->delete(route('settings.account.destroy'), [
            'password' => 'secret-password-123',
        ]);

        $response->assertRedirect(route('gallery'));
        $this->assertGuest();
        $this->assertDatabaseMissing('users', ['id' => $user->id]);
    }

    public function test_user_cannot_delete_account_with_incorrect_password(): void
    {
        $user = User::factory()->create([
            'password' => Hash::make('secret-password-123'),
        ]);

        $response = $this->actingAs($user)->delete(route('settings.account.destroy'), [
            'password' => 'wrong-password',
        ]);

        $response->assertSessionHasErrors('password');
        $this->assertDatabaseHas('users', ['id' => $user->id]);
    }
}
