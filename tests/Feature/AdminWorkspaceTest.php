<?php

namespace Tests\Feature;

use App\Models\AppNotification;
use App\Models\DmcaNotice;
use App\Models\Post;
use App\Models\Report;
use App\Models\User;
use App\Notifications\UrgentReportAlert;
use App\Support\SiteEvents;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Notification;
use Tests\TestCase;

class AdminWorkspaceTest extends TestCase
{
    use RefreshDatabase;

    public function test_admin_can_assign_and_revoke_limited_roles_but_cannot_grant_full_access(): void
    {
        $admin = User::factory()->admin()->create();
        $staff = User::factory()->create();
        $route = route('admin.users.roles', $staff);

        $this->actingAs($admin)->patch($route, [
            'roles' => ['moderator', 'analyst'],
            'reason' => 'Staff appointment',
        ])->assertRedirect();
        $this->assertDatabaseHas('moderation_actions', ['action' => 'change_staff_roles', 'target_id' => $staff->id, 'reason' => 'Staff appointment']);

        $this->actingAs($staff->fresh())->get('/admin')->assertOk();
        $this->get('/admin/analytics')->assertOk();
        $this->get('/admin/users')->assertForbidden();
        $this->get('/admin/dmca')->assertForbidden();
        $this->get('/admin/site-logs')->assertForbidden();
        $this->patch($route, ['roles' => [], 'reason' => 'Self edit'])->assertForbidden();

        $this->actingAs($admin)->patch($route, [
            'roles' => [], 'administrator' => true, 'reason' => 'Escalation',
        ])->assertForbidden();
        $this->actingAs($admin)->patch($route, ['roles' => [], 'reason' => 'Access revoked'])->assertRedirect();
        $this->actingAs($staff->fresh())->get('/admin')->assertForbidden();
    }

    public function test_report_actions_cannot_ban_an_admin_or_act_on_a_closed_report(): void
    {
        $actor = User::factory()->admin()->create();
        $target = User::factory()->admin()->create();
        $report = Report::create([
            'reportable_type' => User::class, 'reportable_id' => $target->id, 'reason' => 'spam',
        ]);

        $this->actingAs($actor)->post(route('admin.reports.remove', $report), ['reason' => 'Spam'])->assertUnprocessable();
        $this->assertNull($target->fresh()->banned_at);
        $this->assertSame('open', $report->fresh()->status);
        $this->post(route('admin.reports.dismiss', $report))->assertRedirect();
        $this->post(route('admin.reports.dismiss', $report))->assertConflict();
        $this->assertDatabaseCount('moderation_actions', 1);
    }

    public function test_urgent_reports_alert_moderators_and_appear_before_older_routine_reports(): void
    {
        config(['admin.urgent_report_email' => 'safety@example.test']);
        Notification::fake();
        $admin = User::factory()->admin()->create();
        $moderator = User::factory()->create();
        $moderator->forceFill(['staff_roles' => ['moderator']])->save();
        $operator = User::factory()->create();
        $operator->forceFill(['staff_roles' => ['operator']])->save();
        $artist = User::factory()->create();
        $post = Post::create(['user_id' => $artist->id, 'type' => 'image', 'rating' => 'sfw']);
        Report::create(['reportable_type' => Post::class, 'reportable_id' => $post->id, 'reason' => 'spam']);

        $this->actingAs(User::factory()->create())->post(route('reports.store'), [
            'reportable_type' => 'post', 'reportable_id' => $post->id, 'reason' => 'minor',
        ])->assertRedirect();
        Notification::assertSentOnDemand(UrgentReportAlert::class,
            fn ($alert, $channels, $notifiable) => $notifiable->routes['mail'] === 'safety@example.test');
        $alertRecipients = AppNotification::where('type', 'urgent_report')->pluck('user_id')->all();
        $this->assertEqualsCanonicalizing([$admin->id, $moderator->id], $alertRecipients);
        $this->actingAs($moderator)->get('/admin')->assertInertia(fn ($page) => $page
            ->where('urgentCount', 1)
            ->has('reports', 1)
            ->where('reports.0.reason', 'minor')
            ->where('reports.0.case_count', 2)
            ->where('reports.0.related_open', 2)
            ->where('reports.0.sensitive', true)
            ->where('reports.0.image', null));
        $this->get(route('admin.reports', ['target_type' => 'Post', 'target_id' => $post->id]))
            ->assertInertia(fn ($page) => $page->has('reports', 2)
                ->where('reports.0.reason', 'minor')
                ->where('reports.1.reason', 'spam')
                ->where('reports.1.sensitive', true));
    }

    public function test_dmca_takedowns_prevent_restoration_until_the_notice_is_resolved(): void
    {
        config(['app.url' => 'https://inkwell.example']);
        $admin = User::factory()->admin()->create();
        $post = Post::create(['user_id' => User::factory()->create()->id, 'type' => 'image', 'rating' => 'sfw']);
        $notice = DmcaNotice::create([
            'reporter_name' => 'Rights holder', 'reporter_email' => 'rights@example.test',
            'url' => 'https://inkwell.example/p/'.$post->id, 'description' => 'A notice concerning the reported work.',
        ]);

        $this->actingAs($admin)->get('/admin/dmca')->assertOk();
        $this->post(route('admin.dmca.update', $notice), ['action' => 'takedown', 'reason' => 'Notice reviewed'])->assertRedirect();
        $this->assertSoftDeleted($post);
        $this->assertDatabaseHas('dmca_notices', ['id' => $notice->id, 'status' => 'actioned', 'post_id' => $post->id]);
        $this->post(route('admin.works.restore', $post->id), ['reason' => 'Review complete'])->assertConflict();
        $this->post(route('admin.dmca.update', $notice), ['action' => 'resolve', 'reason' => 'Hold released'])->assertRedirect();
        $this->post(route('admin.works.restore', $post->id), ['reason' => 'Hold released'])->assertRedirect();
        $this->assertNotSoftDeleted($post);
    }

    public function test_site_logs_expose_metadata_without_exception_messages(): void
    {
        $operator = User::factory()->create();
        $operator->forceFill(['staff_roles' => ['operator']])->save();

        SiteEvents::exception(new \RuntimeException('private text'));
        $this->assertDatabaseHas('site_events', ['exception' => 'RuntimeException']);
        $this->actingAs($operator)->get('/admin/site-logs')->assertInertia(fn ($page) => $page
            ->component('Admin/SiteLogs')->where('events.0.exception', 'RuntimeException')->missing('events.0.message'));
        $this->get('/admin/log')->assertForbidden();
        $this->get('/admin/analytics')->assertForbidden();
    }
}
