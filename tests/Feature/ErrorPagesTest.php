<?php

namespace Tests\Feature;

use App\Models\Post;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ErrorPagesTest extends TestCase
{
    use RefreshDatabase;

    public function test_missing_pages_render_the_branded_error_page_with_the_real_status(): void
    {
        $this->get('/this-page-does-not-exist')->assertNotFound()
            ->assertInertia(fn ($page) => $page->component('Error')->where('status', 404));

        $this->get('/p/999999')->assertNotFound()
            ->assertInertia(fn ($page) => $page->component('Error')->where('status', 404));
    }

    public function test_forbidden_pages_render_the_branded_error_page(): void
    {
        [$owner, $other] = User::factory()->count(2)->create();
        $post = Post::create(['user_id' => $owner->id, 'type' => 'image', 'rating' => 'sfw']);

        $this->actingAs($other)->get(route('posts.edit', $post))->assertForbidden()
            ->assertInertia(fn ($page) => $page->component('Error')->where('status', 403));
    }

    public function test_json_requests_keep_the_plain_json_error(): void
    {
        $this->getJson('/this-page-does-not-exist')->assertNotFound()->assertJsonStructure(['message']);
    }
}
