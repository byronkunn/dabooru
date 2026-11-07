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

    public function test_the_library_is_built_from_the_sfw_files(): void
    {
        $this->assertSame(49 + 23, Post::count(), '49 images + 23 videos, one post each');
        $this->assertGreaterThan(0, Post::where('type', 'video')->count());
        $this->assertSame(0, Post::where('type', 'comic')->count(), 'no invented comic series');

        $post = Post::firstOrFail();
        $url = $post->media->first()->media->url();
        $this->assertStringStartsWith('/sfw/content/', $url);
    }

    public function test_posts_with_no_author_in_the_filename_are_unattributed(): void
    {
        $anonymous = Post::whereNull('user_id')->count();
        $credited = Post::whereNotNull('user_id')->count();

        $this->assertGreaterThan(0, $anonymous, 'hashed file names stay unattributed');
        $this->assertGreaterThan(0, $credited, 'named files get credited');
    }

    public function test_credited_artists_come_from_their_file_names(): void
    {
        $this->assertDatabaseHas('users', ['username' => 'john-staub', 'name' => 'John Staub']);
        $this->assertDatabaseHas('users', ['username' => 'pavel-yankovich', 'name' => 'Pavel Yankovich']);

        $this->assertDatabaseMissing('users', ['username' => 'rin_senpai']);
        $this->assertDatabaseMissing('users', ['username' => 'mira_ink']);
        $this->assertDatabaseMissing('users', ['username' => 'viewer']);
    }

    public function test_the_unsorted_bucket_is_not_offered_as_an_artist(): void
    {
        $unsorted = User::where('username', 'unsorted')->firstOrFail();
        $this->assertTrue($unsorted->is_unsorted);

        // verified() excludes the bucket, so it never appears in suggestions or the switcher
        $this->assertNotNull($unsorted);
        $this->assertSame(0, User::verified()->where('username', 'unsorted')->count());
    }

    public function test_the_login_page_lists_real_library_accounts(): void
    {
        $response = $this->get('/login')->assertOk();

        $accounts = $response->viewData('page')['props']['demoAccounts'];
        $this->assertNotEmpty($accounts);
        $this->assertLessThanOrEqual(3, count($accounts));

        foreach ($accounts as $account) {
            $this->assertNotSame('unsorted', $account['username']);
            $this->assertNotEmpty($account['name']);
        }
    }

    public function test_one_click_login_works_for_a_listed_account(): void
    {
        $username = User::verified()->has('posts')->value('username');

        $this->get('/demo-login/'.$username)->assertRedirect(route('gallery'));
        $this->assertAuthenticatedAs(User::where('username', $username)->first());
    }

    public function test_unknown_demo_username_is_rejected(): void
    {
        $this->get('/demo-login/unsorted')->assertNotFound();
        $this->get('/demo-login/someone-else')->assertNotFound();
        $this->assertGuest();
    }
}
