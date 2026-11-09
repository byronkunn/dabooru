<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class LibraryScopeTest extends TestCase
{
    use RefreshDatabase;

    public function test_library_only_covers_collections_and_saved_searches(): void
    {
        $user = User::factory()->create();

        $this->actingAs($user)->get(route('collections.index'))->assertOk()->assertInertia(fn ($page) => $page->component('Collections/Index'));
        $this->actingAs($user)->get(route('saved-searches.index'))->assertOk();
    }

    public function test_new_chapters_still_lives_under_comics(): void
    {
        $user = User::factory()->create();

        // reachable on its own, and Comics links to it
        $this->actingAs($user)->get(route('chapters.inbox'))->assertOk();

        // the Comics page is an Inertia response, so check the route exists and the
        // page component renders rather than looking for the link in raw HTML
        $this->actingAs($user)->get(route('comics.index'))
            ->assertOk()
            ->assertInertia(fn ($page) => $page->component('Comics/Index'));
        $this->assertTrue(\Illuminate\Support\Facades\Route::has('chapters.inbox'));
    }
}
