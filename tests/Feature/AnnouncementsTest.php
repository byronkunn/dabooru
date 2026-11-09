<?php

namespace Tests\Feature;

use App\Models\Announcement;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class AnnouncementsTest extends TestCase
{
    use RefreshDatabase;

    private function admin(): User
    {
        $admin = User::factory()->create();
        $admin->forceFill(['is_admin' => true])->save();

        return $admin;
    }

    public function test_only_administrators_can_manage_announcements(): void
    {
        $announcement = Announcement::create(['title' => 'News', 'body' => 'Site news.', 'published' => false]);
        $this->get(route('admin.announcements.index'))->assertRedirect(route('login'));
        $this->actingAs(User::factory()->create());
        $this->get(route('admin.announcements.index'))->assertForbidden();
        $this->post(route('admin.announcements.store'), [])->assertForbidden();
        $this->patch(route('admin.announcements.update', $announcement), [])->assertForbidden();
        $this->delete(route('admin.announcements.destroy', $announcement))->assertForbidden();
    }

    public function test_admin_can_create_update_unpublish_and_delete_announcements(): void
    {
        $this->actingAs($this->admin());
        $this->get(route('admin.announcements.index'))->assertOk()
            ->assertInertia(fn ($page) => $page->component('Admin/Announcements'));
        $data = ['title' => 'Welcome', 'body' => 'New site features.', 'published' => true];
        $this->post(route('admin.announcements.store'), $data)->assertSessionHasNoErrors()->assertRedirect();
        $announcement = Announcement::firstOrFail();
        $this->assertTrue($announcement->published);
        $this->get('/comics')->assertInertia(fn ($page) => $page->where('announcement.title', 'Welcome'));
        $data['published'] = false;
        $this->patch(route('admin.announcements.update', $announcement), $data)->assertSessionHasNoErrors()->assertRedirect();
        $this->get('/comics')->assertInertia(fn ($page) => $page->where('announcement', null));
        $this->delete(route('admin.announcements.destroy', $announcement))->assertRedirect();
        $this->assertDatabaseMissing('announcements', ['id' => $announcement->id]);
    }

    public function test_public_pages_share_only_latest_published_announcement_and_sponsor_configuration(): void
    {
        Announcement::create(['title' => 'Old news', 'body' => 'Old public message', 'published' => true]);
        $latest = Announcement::create(['title' => 'Latest news', 'body' => '<script>alert(1)</script>', 'published' => true]);
        Announcement::create(['title' => 'Secret draft', 'body' => 'Not public', 'published' => false]);
        config(['ads.image' => '/sponsor.png', 'ads.url' => 'https://example.com', 'ads.label' => 'Test sponsor']);
        $this->get('/comics')->assertInertia(fn ($page) => $page
            ->where('announcement.id', $latest->id)
            ->where('announcement.body', '<script>alert(1)</script>')
            ->where('sponsorAd.image', '/sponsor.png')
            ->where('sponsorAd.url', 'https://example.com')
            ->where('sponsorAd.label', 'Test sponsor'));
    }

    public function test_announcements_validate_required_fields_and_length_limits(): void
    {
        $this->actingAs($this->admin());
        $this->post(route('admin.announcements.store'), [])->assertSessionHasErrors(['title', 'body', 'published']);
        $this->post(route('admin.announcements.store'), [
            'title' => str_repeat('a', 121), 'body' => str_repeat('b', 1001), 'published' => 'invalid',
        ])->assertSessionHasErrors(['title', 'body', 'published']);
        $this->assertDatabaseCount('announcements', 0);
    }
}
