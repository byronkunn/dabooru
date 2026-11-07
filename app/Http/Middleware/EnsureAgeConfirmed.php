<?php

namespace App\Http\Middleware;

use App\Support\AgeGate;
use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cookie;
use Symfony\Component\HttpFoundation\Response;

class EnsureAgeConfirmed
{
    public function handle(Request $request, Closure $next): Response
    {
        // Feature tests run with the gate bypassed by default; set
        // INKWELL_AGE_GATE_TEST_BYPASS=false to exercise it.
        if (app()->runningUnitTests() && config('app.age_gate_test_bypass', true)) {
            return $next($request);
        }

        // A banned account loses access immediately, even mid-session.
        if ($request->user() && $request->user()->banned_at) {
            auth()->logout();
            $request->session()->invalidate();
            $request->session()->regenerateToken();

            return redirect()->route('login')->with('error', 'This account has been suspended.');
        }

        // Auth, the gate itself and asset routes are always reachable.
        if (AgeGate::confirmed()) {
            return $next($request);
        }

        // The gate and the auth pages are always reachable, logged out or not.
        if ($request->is(
            'age-gate*', 'login', 'register', 'forgot-password*', 'reset-password*',
            'terms', 'privacy', 'content-policy', 'dmca*',
            'up', 'build/*', 'storage/*', 'sfw/*',
        )) {
            return $next($request);
        }

        return redirect()->to('/age-gate')
            ->withCookie(Cookie::make(AgeGate::COOKIE, null, -1));
    }
}
