<?php

namespace App\Support;

use App\Models\User;

/**
 * One-click sign-in for the accounts that actually exist in this library.
 *
 * These are the artists credited in the sfw/ file names. They are library
 * accounts, not people you can log into unless you set a password for them —
 * the quick login is a local-development shortcut only and is disabled when
 * APP_ENV=production.
 */
class DemoAccounts
{
    public static function users(): array
    {
        return User::query()
            ->verified()
            ->has('posts')
            ->withCount('posts')
            ->orderBy('name')
            ->take(3)
            ->get()
            ->map(fn (User $user) => [
                'username' => $user->username,
                'name' => $user->name,
                'email' => $user->email,
                'role' => 'Artist · '.$user->posts_count.' work'.($user->posts_count === 1 ? '' : 's'),
                'blurb' => 'Credited in the sfw/ sample library.',
                'avatar' => $user->avatarUrl(),
                'url' => route('demo.login', $user->username),
            ])
            ->all();
    }

    public static function usernames(): array
    {
        return collect(self::users())->pluck('username')->all();
    }
}
