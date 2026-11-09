<?php

namespace Tests\Feature;

use App\Models\Post;
use App\Models\Report;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ReporterStatusTest extends TestCase
{
    use RefreshDatabase;

    private function report(User $reporter, array $overrides = []): Report
    {
        $post = Post::create(['user_id' => $reporter->id, 'type' => 'image', 'rating' => 'sfw', 'title' => 'Reported']);

        $report = Report::create([
            'reporter_id' => $reporter->id,
            'reportable_type' => Post::class,
            'reportable_id' => $post->id,
            'reason' => 'spam',
            'status' => 'open',
        ]);

        // assigned_to / handled_by / resolved_at are not mass-assignable, the
        // moderation code sets them with forceFill, so the fixture does too.
        if ($overrides !== []) {
            $report->forceFill($overrides)->save();
        }

        return $report->refresh();
    }

    public function test_the_page_starts_empty(): void
    {
        $user = User::factory()->create();

        $this->actingAs($user)->get(route('reports.index'))
            ->assertOk()
            ->assertInertia(fn ($page) => $page->component('Reports/Index')->has('reports.data', 0));
    }

    public function test_states_map_from_the_moderation_queue(): void
    {
        $reporter = User::factory()->create();
        $moderator = User::factory()->create(['is_admin' => true]);

        $received = $this->report($reporter);
        $claimed = $this->report($reporter, ['assigned_to' => $moderator->id]);
        $actioned = $this->report($reporter, ['status' => 'actioned', 'resolved_at' => now()]);
        $dismissed = $this->report($reporter, ['status' => 'dismissed', 'resolved_at' => now()]);

        $this->actingAs($reporter)->get(route('reports.index'))
            ->assertOk()
            ->assertInertia(fn ($page) => $page
                ->has('reports.data', 4)
                ->where('summary.received', 1)
                ->where('summary.under_review', 1)
                ->where('summary.closed', 2)
                ->where('reports.data', fn ($rows) => collect($rows)->pluck('state')->sort()->values()->all()
                    === ['closed', 'closed', 'received', 'under_review']));
    }

    public function test_reports_are_private_to_their_reporter(): void
    {
        $reporter = User::factory()->create();
        $nosy = User::factory()->create();
        $this->report($reporter);

        $this->actingAs($nosy)->get(route('reports.index'))
            ->assertOk()
            ->assertInertia(fn ($page) => $page->has('reports.data', 0));
    }

    public function test_guests_are_sent_to_login(): void
    {
        $this->get(route('reports.index'))->assertRedirect(route('login'));
    }

    public function test_a_closed_report_does_not_leak_the_handler(): void
    {
        $reporter = User::factory()->create();
        $moderator = User::factory()->create(['is_admin' => true, 'username' => 'secretmod']);
        $this->report($reporter, ['status' => 'actioned', 'handled_by' => $moderator->id, 'resolved_at' => now()]);

        $response = $this->actingAs($reporter)->get(route('reports.index'));
        $response->assertOk();
        $this->assertStringNotContainsString('secretmod', $response->getContent());
    }
}
