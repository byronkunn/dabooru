<?php

namespace Tests\Feature;

use App\Models\Post;
use App\Models\User;
use Database\Seeders\DatabaseSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class DemoAccountsTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(DatabaseSeeder::class);
    }

    public function test_three_demo_accounts_exist_with_distinct_roles(): void
    {
        $this->assertSame(3, User::count());

        $viewer = User::where('username', 'viewer')->firstOrFail();
        $this->assertSame(0, $viewer->posts()->count(), 'the viewer account does not upload');

        $this->assertGreaterThan(0, User::where('username', 'mira_ink')->firstOrFail()->posts()->count());
        $this->assertGreaterThan(0, User::where('username', 'rin_senpai')->firstOrFail()->posts()->count());
    }

    public function test_the_login_page_lists_all_three_accounts(): void
    {
        $this->get('/login')
            ->assertOk()
            ->assertInertia(fn ($page) => $page
                ->component('Auth/Login')
                ->has('demoAccounts', 3)
                ->where('demoAccounts.0.username', 'mira_ink')
                ->where('demoAccounts.2.username', 'viewer'));
    }

    public function test_one_click_login_signs_in_as_each_account(): void
    {
        foreach (['mira_ink', 'rin_senpai', 'viewer'] as $username) {
            $this->post('/logout');
            $this->get('/demo-login/'.$username)->assertRedirect(route('gallery'));
            $this->assertAuthenticatedAs(User::where('username', $username)->firstOrFail());
        }
    }

    public function test_unknown_demo_username_is_rejected(): void
    {
        $this->get('/demo-login/someone-else')->assertNotFound();
        $this->assertGuest();
    }

    public function test_seeded_content_uses_local_sample_files(): void
    {
        $post = Post::where('type', 'image')->firstOrFail();
        $url = $post->media->first()->media->url();

        $this->assertStringStartsWith('/sfw/content/image/', $url);
        $this->assertFileExists(base_path('sfw/image/'.basename($url)));
    }
}
