<?php

namespace Tests\Feature\Auth;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class RegistrationTest extends TestCase
{
    use RefreshDatabase;

    private function payload(array $overrides = []): array
    {
        return array_merge([
            'name' => 'Test User',
            'username' => 'test_user',
            'email' => 'test@example.com',
            'password' => 'password123',
            'password_confirmation' => 'password123',
            'date_of_birth' => now()->subYears(24)->toDateString(),
            'age_confirmed' => true,
        ], $overrides);
    }

    public function test_registration_screen_can_be_rendered(): void
    {
        $this->get('/register')->assertStatus(200);
    }

    public function test_new_users_can_register(): void
    {
        $this->post('/register', $this->payload())->assertRedirect(route('gallery', absolute: false));

        $this->assertAuthenticated();
        $user = User::where('username', 'test_user')->firstOrFail();
        $this->assertNotNull($user->age_confirmed_at);
    }

    public function test_underage_registration_is_rejected(): void
    {
        $this->post('/register', $this->payload(['date_of_birth' => now()->subYears(17)->toDateString()]))
            ->assertSessionHasErrors('date_of_birth');

        $this->assertGuest();
        $this->assertDatabaseCount('users', 0);
    }

    public function test_age_checkbox_is_required(): void
    {
        $this->post('/register', $this->payload(['age_confirmed' => false]))
            ->assertSessionHasErrors('age_confirmed');

        $this->assertGuest();
    }

    public function test_username_must_be_unique(): void
    {
        User::factory()->create(['username' => 'taken']);

        $this->post('/register', $this->payload(['username' => 'taken']))
            ->assertSessionHasErrors('username');
    }
}
