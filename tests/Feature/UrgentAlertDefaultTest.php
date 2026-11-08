<?php

namespace Tests\Feature;

use App\Notifications\UrgentReportAlert;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Notification;
use Tests\TestCase;

/**
 * The urgent-report alert is a safety path. It silently did nothing in a real
 * install because the address was never configured and the notification was
 * queued with no worker running. These tests guard both halves.
 */
class UrgentAlertDefaultTest extends TestCase
{
    use RefreshDatabase;

    public function test_the_alert_is_not_queued(): void
    {
        // a queued notification never arrives without a queue worker
        $this->assertFalse(
            is_subclass_of(UrgentReportAlert::class, \Illuminate\Contracts\Queue\ShouldQueue::class),
            'Urgent moderation alerts must be sent immediately, not queued.'
        );
    }

    public function test_the_urgent_address_is_documented_in_the_example_env(): void
    {
        $example = file_get_contents(base_path('.env.example'));

        $this->assertStringContainsString('ADMIN_URGENT_REPORT_EMAIL', $example, 'The urgent alert address must be discoverable in .env.example.');
    }

    public function test_an_urgent_report_sends_the_alert_immediately(): void
    {
        Notification::fake();
        config(['admin.urgent_report_email' => 'safety@example.test']);

        $reporter = \App\Models\User::factory()->create();
        $post = \App\Models\Post::create([
            'user_id' => $reporter->id, 'type' => 'image', 'rating' => 'sfw', 'title' => 'Reported',
        ]);

        $this->actingAs($reporter)->post(route('reports.store'), [
            'reportable_type' => 'post',
            'reportable_id' => $post->id,
            'reason' => 'minor',
        ])->assertRedirect();

        Notification::assertSentOnDemand(UrgentReportAlert::class);
        // and nothing was handed to the queue
        $this->assertDatabaseCount('jobs', 0);
    }
}
