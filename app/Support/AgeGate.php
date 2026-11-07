<?php

namespace App\Support;

use Illuminate\Support\Facades\Cookie;

class AgeGate
{
    public const COOKIE = 'inkwell_age_ok';

    /** How long the confirmation lasts, in minutes (one year). */
    public const MINUTES = 60 * 24 * 365;

    public static function confirmed(): bool
    {
        if (auth()->check() && auth()->user()->age_confirmed_at) {
            return true;
        }

        return (bool) Cookie::get(self::COOKIE);
    }

    public static function confirm(): void
    {
        Cookie::queue(Cookie::make(self::COOKIE, '1', self::MINUTES, httpOnly: true, sameSite: 'lax'));

        if (auth()->check() && ! auth()->user()->age_confirmed_at) {
            auth()->user()->forceFill(['age_confirmed_at' => now()])->save();
        }
    }

    public static function forget(): void
    {
        Cookie::queue(Cookie::forget(self::COOKIE));
    }
}
