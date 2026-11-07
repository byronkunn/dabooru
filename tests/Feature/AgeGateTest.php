<?php

namespace Tests\Feature;

use App\Support\AgeGate;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class AgeGateTest extends TestCase
{
    use RefreshDatabase;

    public function test_guests_are_sent_to_the_gate(): void
    {
        config(['app.age_gate_test_bypass' => false]);

        $this->get('/')->assertRedirect('/age-gate');
    }

    public function test_gate_page_renders(): void
    {
        $this->get('/age-gate')->assertOk()->assertInertia(fn ($page) => $page->component('Gate'));
    }

    public function test_confirming_the_gate_sets_the_cookie_and_redirects_home(): void
    {
        $this->post('/age-gate')->assertRedirect('/');

        $response = $this->get('/age-gate');
        $response->assertOk();
    }
}
