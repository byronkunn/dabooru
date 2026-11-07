<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

class SettingsTest extends TestCase
{
    use RefreshDatabase;

    public function test_user_can_delete_their_account_with_correct_password(): void
    {
        $user = User::factory()->create([
            'password' => Hash::make('secret-password-123'),
        ]);

        $response = $this->actingAs($user)->delete(route('settings.account.destroy'), [
            'password' => 'secret-password-123',
        ]);

        $response->assertRedirect(route('gallery'));
        $this->assertGuest();
        $this->assertDatabaseMissing('users', ['id' => $user->id]);
    }

    public function test_user_cannot_delete_account_with_incorrect_password(): void
    {
        $user = User::factory()->create([
            'password' => Hash::make('secret-password-123'),
        ]);

        $response = $this->actingAs($user)->delete(route('settings.account.destroy'), [
            'password' => 'wrong-password',
        ]);

        $response->assertSessionHasErrors('password');
        $this->assertDatabaseHas('users', ['id' => $user->id]);
    }
}
