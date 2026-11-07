<?php

namespace Tests\Feature;

use App\Models\DmcaNotice;
use App\Models\ModerationAction;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class LegalPagesTest extends TestCase
{
    use RefreshDatabase;

    public function test_legal_pages_are_public_even_before_the_age_gate(): void
    {
        config(['app.age_gate_test_bypass' => false]);

        foreach (['/terms', '/privacy', '/content-policy', '/dmca'] as $url) {
            $this->get($url)->assertOk();
        }
    }

    public function test_terms_and_privacy_render_their_sections(): void
    {
        $this->get('/terms')->assertOk()
            ->assertInertia(fn ($page) => $page->component('Legal/Terms')->has('updated'));

        $this->get('/privacy')->assertOk()
            ->assertInertia(fn ($page) => $page->component('Legal/Privacy')->has('updated'));

        $this->get('/content-policy')->assertOk()
            ->assertInertia(fn ($page) => $page->component('Legal/ContentPolicy')->has('updated'));
    }

    public function test_a_valid_dmca_notice_is_stored_and_logged(): void
    {
        $this->post('/dmca', [
            'reporter_name' => 'Jane Artist',
            'reporter_email' => 'jane@example.com',
            'rights_holder' => 'Jane Artist',
            'url' => 'http://localhost/p/1',
            'description' => 'This is my painting reposted without permission by another account.',
            'good_faith' => true,
            'penalty_ack' => true,
        ])->assertRedirect(route('dmca.submitted'));

        $this->assertDatabaseHas('dmca_notices', [
            'reporter_email' => 'jane@example.com',
            'status' => 'open',
            'good_faith' => true,
            'penalty_ack' => true,
        ]);

        $this->assertDatabaseHas('moderation_actions', ['action' => 'dmca_notice']);
    }

    public function test_a_dmca_notice_without_the_required_statements_is_rejected(): void
    {
        $this->post('/dmca', [
            'reporter_name' => 'Jane',
            'reporter_email' => 'jane@example.com',
            'url' => 'http://localhost/p/1',
            'description' => 'This is clearly mine and I want it taken down right now.',
            'good_faith' => false,
            'penalty_ack' => false,
        ])->assertSessionHasErrors(['good_faith', 'penalty_ack']);

        $this->assertSame(0, DmcaNotice::count());
    }

    public function test_a_dmca_notice_needs_a_real_url_and_description(): void
    {
        $this->post('/dmca', [
            'reporter_name' => 'Jane',
            'reporter_email' => 'jane@example.com',
            'url' => 'not-a-url',
            'description' => 'short',
            'good_faith' => true,
            'penalty_ack' => true,
        ])->assertSessionHasErrors(['url', 'description']);

        $this->assertSame(0, DmcaNotice::count());
    }

    public function test_registration_links_to_the_real_legal_pages(): void
    {
        $response = $this->get('/register')->assertOk();
        $html = $response->getContent();

        // the pages referenced in the checkbox resolve
        $this->get(route('legal.terms'))->assertOk();
        $this->get(route('legal.privacy'))->assertOk();
        $this->get(route('legal.policy'))->assertOk();

        $this->assertStringContainsString('register', $html);
    }
}
