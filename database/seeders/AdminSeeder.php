<?php

namespace Database\Seeders;

use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;

/**
 * A moderator account you can actually log into. Local development only —
 * delete or re-password this before launch (see docs/launch-checklist.md).
 */
class AdminSeeder extends Seeder
{
    public function run(): void
    {
        $admin = User::firstOrCreate(
            ['username' => 'moderator'],
            [
                'name' => 'Moderator',
                'email' => 'mod@modernbooru.test',
                'password' => Hash::make('password'),
                'bio' => 'Site moderator.',
            ]
        );

        $admin->forceFill([
            'email_verified_at' => now(),
            'age_confirmed_at' => now(),
            'is_admin' => true,
        ])->save();

        $this->command?->info('Admin login: mod@modernbooru.test / password');
    }
}
