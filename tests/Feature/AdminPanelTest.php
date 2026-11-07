<?php

namespace Tests\Feature;

use App\Models\Block;
use App\Models\Comment;
use App\Models\DmcaNotice;
use App\Models\Message;
use App\Models\ModerationAction;
use App\Models\Post;
use App\Models\Report;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class AdminPanelTest extends TestCase
{
    use RefreshDatabase;

    private function mod(): User
    {
        return User::where('username', 'mod')->first()
            ?? User::factory()->admin()->create(['username' => 'mod']);
    }

    private function reportedPost(): array
    {
        $artist = User::factory()->create(['username' => 'artist']);
        $reporter = User::factory()->create(['username' => 'reporter']);
        $post = Post::create(['user_id' => $artist->id, 'type' => 'image', 'rating' => 'explicit', 'title' => 'Bad work']);
        $report = Report::create([
            'reporter_id' => $reporter->id,
            'reportable_type' => Post::class,
            'reportable_id' => $post->id,
            'reason' => 'non_consensual',
            'details' => 'stolen',
        ]);

        return compact('artist', 'reporter', 'post', 'report');
    }

    /* ------------------------------------------------------------ access */

    public function test_admin_pages_reject_guests(): void
    {
        $this->get('/admin')->assertRedirect('/login');
    }

    public function test_admin_pages_reject_normal_users(): void
    {
        $this->actingAs(User::factory()->create())->get('/admin')->assertForbidden();
        $this->actingAs(User::factory()->create())->get('/admin/users')->assertForbidden();
        $this->actingAs(User::factory()->create())->get('/admin/log')->assertForbidden();
    }

    public function test_admin_can_open_the_queue(): void
    {
        ['report' => $report] = $this->reportedPost();

        $this->actingAs($this->mod())
            ->get('/admin')
            ->assertOk()
            ->assertInertia(fn ($page) => $page
                ->component('Admin/Reports')
                ->where('counts.open', 1)
                ->has('reports', 1)
                ->where('reports.0.id', $report->id)
                ->where('reports.0.reason_label', 'Non-consensual or stolen'));
    }

    /* ------------------------------------------------------------ removal */

    public function test_removing_a_reported_post_soft_deletes_it_and_logs_the_action(): void
    {
        ['post' => $post, 'report' => $report] = $this->reportedPost();

        $this->actingAs($this->mod())
            ->post(route('admin.reports.remove', $report), ['reason' => 'stolen art'])
            ->assertRedirect();

        $this->assertSoftDeleted('posts', ['id' => $post->id]);
        $this->assertSame('actioned', $report->fresh()->status);
        $this->assertNotNull($report->fresh()->resolved_at);
        $this->assertDatabaseHas('moderation_actions', ['action' => 'remove_post', 'report_id' => $report->id, 'reason' => 'stolen art']);

        // and it is gone from the public gallery
        $this->get('/')->assertInertia(fn ($page) => $page->has('posts', 0));
    }

    public function test_removing_a_reported_user_bans_them(): void
    {
        $user = User::factory()->create(['username' => 'spammer']);
        $report = Report::create([
            'reporter_id' => User::factory()->create()->id,
            'reportable_type' => User::class,
            'reportable_id' => $user->id,
            'reason' => 'spam',
        ]);

        $this->actingAs($this->mod())->post(route('admin.reports.remove', $report), ['reason' => 'spam'])->assertRedirect();

        $this->assertNotNull($user->fresh()->banned_at);
        $this->assertDatabaseHas('moderation_actions', ['action' => 'ban_user', 'target_id' => $user->id]);
    }

    public function test_dismissing_a_report_keeps_the_content(): void
    {
        ['post' => $post, 'report' => $report] = $this->reportedPost();

        $this->actingAs($this->mod())->post(route('admin.reports.dismiss', $report), ['reason' => 'not a violation'])->assertRedirect();

        $this->assertNull($post->fresh()->deleted_at);
        $this->assertSame('dismissed', $report->fresh()->status);
        $this->assertDatabaseHas('moderation_actions', ['action' => 'dismiss_report']);
    }

    /* --------------------------------------------------------------- bans */

    public function test_admin_can_ban_and_unban(): void
    {
        $user = User::factory()->create(['username' => 'target']);
        $mod = $this->mod();

        $this->actingAs($mod)->post(route('admin.users.ban', $user), ['reason' => 'abuse'])->assertRedirect();
        $this->assertNotNull($user->fresh()->banned_at);

        $this->actingAs($mod)->post(route('admin.users.unban', $user))->assertRedirect();
        $this->assertNull($user->fresh()->banned_at);
    }

    public function test_ban_requires_a_reason(): void
    {
        $user = User::factory()->create();

        $this->actingAs($this->mod())->post(route('admin.users.ban', $user), [])->assertSessionHasErrors('reason');
        $this->assertNull($user->fresh()->banned_at);
    }

    public function test_an_admin_cannot_ban_themselves_or_another_admin(): void
    {
        $mod = $this->mod();
        $otherAdmin = User::factory()->admin()->create();

        $this->actingAs($mod)->post(route('admin.users.ban', $mod), ['reason' => 'x'])->assertStatus(422);
        $this->actingAs($mod)->post(route('admin.users.ban', $otherAdmin), ['reason' => 'x'])->assertStatus(422);
    }

    /* ---------------------------------------------------------------- log */

    public function test_the_log_lists_actions_and_never_loses_them(): void
    {
        ['report' => $report] = $this->reportedPost();
        $this->actingAs($this->mod())->post(route('admin.reports.dismiss', $report), []);
        $this->actingAs($this->mod())->post(route('admin.reports.dismiss', $report), []);

        $this->assertGreaterThanOrEqual(1, ModerationAction::count());
        $this->actingAs($this->mod())->get('/admin/log')->assertOk()
            ->assertInertia(fn ($page) => $page->component('Admin/Log')->has('actions'));
    }
}
