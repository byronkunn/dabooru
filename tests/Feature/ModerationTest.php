<?php

namespace Tests\Feature;

use App\Models\Post;
use App\Models\Report;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ModerationTest extends TestCase
{
    use RefreshDatabase;

    public function test_a_user_can_report_a_post(): void
    {
        $post = Post::create(['user_id' => User::factory()->create()->id, 'type' => 'image', 'rating' => 'sfw']);
        $reporter = User::factory()->create();

        $this->actingAs($reporter)->post(route('reports.store'), [
            'reportable_type' => 'post',
            'reportable_id' => $post->id,
            'reason' => 'non_consensual',
            'details' => 'posted without permission',
        ])->assertRedirect();

        $this->assertDatabaseHas('reports', [
            'reportable_id' => $post->id,
            'reason' => 'non_consensual',
            'status' => 'open',
        ]);
    }

    public function test_reports_require_a_known_reason(): void
    {
        $post = Post::create(['user_id' => User::factory()->create()->id, 'type' => 'image', 'rating' => 'sfw']);

        $this->actingAs(User::factory()->create())->post(route('reports.store'), [
            'reportable_type' => 'post',
            'reportable_id' => $post->id,
            'reason' => 'i just do not like it',
        ])->assertSessionHasErrors('reason');

        $this->assertSame(0, Report::count());
    }

    public function test_a_banned_user_is_logged_out(): void
    {
        $user = User::factory()->create(['banned_at' => now()]);
        config(['app.age_gate_test_bypass' => false]);

        $this->actingAs($user)->get('/')->assertRedirect('/login');
        $this->assertGuest();
    }
}
