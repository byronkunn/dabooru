<?php

namespace Tests\Feature;

use App\Models\BugReport;
use App\Models\User;
use App\Support\TipAddresses;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class TipAndBugTest extends TestCase
{
    use RefreshDatabase;

    private function owner(): User
    {
        $owner = User::factory()->create(['is_admin' => true]);
        config(['admin.owner_id' => $owner->id]);

        return $owner;
    }

    public function test_only_the_admin_can_set_tip_addresses(): void
    {
        $owner = $this->owner();
        $someone = User::factory()->create();

        $this->actingAs($someone)->patch(route('tip.update'), [
            'addresses' => ['bitcoin' => 'bc1qexample'],
        ])->assertForbidden();

        $this->actingAs($owner)->patch(route('tip.update'), [
            'addresses' => ['monero' => '4AdUndX', 'bitcoin' => 'bc1qexample', 'ethereum' => '0xabc', 'solana' => '7EcDh'],
        ])->assertRedirect();

        $this->assertSame(['monero', 'bitcoin', 'ethereum', 'solana'], array_keys(TipAddresses::stored($owner)));
    }

    public function test_a_visitor_sees_labels_but_not_the_addresses(): void
    {
        $owner = $this->owner();
        TipAddresses::save($owner, ['monero' => '4AdUndXsecret']);

        $this->get(route('tip'))
            ->assertOk()
            ->assertInertia(fn ($page) => $page
                ->component('Tip/Index')
                ->has('networks', 4)
                ->where('networks.0.network', 'monero')
                ->where('networks.0.configured', true)
                ->where('networks.0.address', null)
                ->where('isOwner', false));

        // the address itself must not appear anywhere in the page
        $this->assertStringNotContainsString('4AdUndXsecret', $this->get(route('tip'))->getContent());
    }

    public function test_the_owner_sees_their_own_addresses(): void
    {
        $owner = $this->owner();
        TipAddresses::save($owner, ['monero' => '4AdUndXsecret']);

        $this->actingAs($owner)->get(route('tip'))
            ->assertOk()
            ->assertInertia(fn ($page) => $page
                ->where('isOwner', true)
                ->where('networks.0.address', '4AdUndXsecret'));
    }

    public function test_unsigned_addresses_are_not_stored(): void
    {
        $owner = $this->owner();
        TipAddresses::save($owner, ['monero' => '   ', 'bitcoin' => 'bc1qok']);

        $this->assertSame(['bitcoin'], array_keys(TipAddresses::stored($owner)));
    }

    public function test_a_bug_report_can_be_filed_signed_out(): void
    {
        $this->post(route('bug.store'), [
            'kind' => 'bug',
            'title' => 'Upload fails on mobile',
            'details' => 'Nothing happens when I press publish.',
            'page_url' => 'http://localhost/upload',
            'viewport' => '390x844',
        ])->assertRedirect();

        $report = BugReport::sole();
        $this->assertSame('bug', $report->kind);
        $this->assertNull($report->user_id, 'signed-out reports are kept, just unattributed');
        $this->assertSame('open', $report->status);
    }

    public function test_a_bug_report_is_validated(): void
    {
        $this->post(route('bug.store'), ['kind' => 'nonsense', 'title' => 'x'])
            ->assertSessionHasErrors(['kind', 'title']);
    }

    public function test_reports_are_listed_back_to_the_person_who_filed_them(): void
    {
        $user = User::factory()->create();
        $this->actingAs($user)->post(route('bug.store'), ['kind' => 'idea', 'title' => 'A tag cloud please']);

        $this->actingAs($user)->get(route('bug.index'))
            ->assertOk()
            ->assertInertia(fn ($page) => $page->has('recent', 1)->where('recent.0.title', 'A tag cloud please'));
    }

    public function test_another_person_cannot_see_your_reports(): void
    {
        $user = User::factory()->create();
        $this->actingAs($user)->post(route('bug.store'), ['kind' => 'bug', 'title' => 'Mine only']);

        $this->actingAs(User::factory()->create())->get(route('bug.index'))
            ->assertInertia(fn ($page) => $page->has('recent', 0));
    }
}
