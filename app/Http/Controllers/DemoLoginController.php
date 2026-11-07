<?php

namespace App\Http\Controllers;

use App\Support\AgeGate;
use App\Support\DemoAccounts;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\Auth;

/**
 * One-click sign-in for the demo accounts. Local development only — the
 * route is registered behind app()->environment() checks.
 */
class DemoLoginController extends Controller
{
    public function login(string $username): RedirectResponse
    {
        abort_unless(in_array($username, array_column(DemoAccounts::ACCOUNTS, 'username'), true), 404);

        $user = \App\Models\User::where('username', $username)->firstOrFail();

        Auth::login($user);
        request()->session()->regenerate();
        AgeGate::confirm();

        return redirect()->route('gallery')->with('success', "Signed in as {$user->name}.");
    }
}
