<?php

namespace Database\Seeders;

use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;

/**
 * Accounts the site test plan asks for. Local QA only.
 *
 * creator        verified creator with upload rights
 * reader         verified reader, show-explicit OFF
 * second         second reader, can be blocked
 * unverified     registered but email not confirmed
 * staff-admin    administrator (also gets owner_id in config/admin.php)
 * staff-moderator/curator/analyst/operator  limited roles
 */
class QaAccountsSeeder extends Seeder
{
    public function run(): void
    {
        $accounts = [
            ['creator', 'QA Creator', true, false, []],
            ['reader', 'QA Reader', true, false, []],
            ['second', 'QA Second Reader', true, false, []],
            ['unverified', 'QA Unverified', false, false, []],
            ['staff-admin', 'QA Administrator', true, true, []],
            ['staff-moderator', 'QA Moderator', true, false, ['moderator']],
            ['staff-curator', 'QA Curator', true, false, ['curator']],
            ['staff-analyst', 'QA Analyst', true, false, ['analyst']],
            ['staff-operator', 'QA Operator', true, false, ['operator']],
        ];

        foreach ($accounts as [$username, $name, $verified, $admin, $roles]) {
            $user = User::firstOrCreate(['username' => $username], [
                'name' => $name,
                'email' => $username.'@qa.test',
                'password' => Hash::make('password'),
                'bio' => 'QA account.',
            ]);

            $user->forceFill([
                'email_verified_at' => $verified ? now() : null,
                'age_confirmed_at' => now(),
                'is_admin' => $admin,
                'staff_roles' => $roles,
                'show_explicit' => $username !== 'reader',
            ])->save();
        }

        $owner = User::where('username', 'staff-admin')->first();
        $this->command?->info('QA accounts created (password: password). Administrator id: '.$owner->id.' — set ADMIN_OWNER_ID='.$owner->id.' for owner-only tests.');
    }
}
