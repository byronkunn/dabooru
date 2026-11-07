<?php

namespace App\Support;

use App\Models\User;

/**
 * The three demo identities shipped with the app. Local development only:
 * the quick-login route is disabled in production.
 */
class DemoAccounts
{
    public const ACCOUNTS = [
        [
            'username' => 'mira_ink',
            'name' => 'mira.ink',
            'email' => 'demo@inkwell.test',
            'role' => 'Artist · owner',
            'blurb' => 'Posts comics, studies and a few explicit pieces. This is the account to use if you want to upload.',
        ],
        [
            'username' => 'rin_senpai',
            'name' => 'Rin Senpai',
            'email' => 'rin_senpai@inkwell.test',
            'role' => 'Artist · manga',
            'blurb' => 'Runs the longest-running comic series and posts new chapters here.',
        ],
        [
            'username' => 'viewer',
            'name' => 'Sam Viewer',
            'email' => 'viewer@inkwell.test',
            'role' => 'Viewer',
            'blurb' => 'A regular visitor: follows artists, likes, comments and reads comics. Does not upload.',
        ],
    ];

    public static function users(): array
    {
        return collect(self::ACCOUNTS)
            ->map(function (array $account) {
                $user = User::where('username', $account['username'])->first();

                return $user ? array_merge($account, [
                    'avatar' => $user->avatarUrl(),
                    'url' => route('demo.login', $account['username']),
                ]) : null;
            })
            ->filter()
            ->values()
            ->all();
    }
}
