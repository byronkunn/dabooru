<?php

namespace App\Http\Controllers;

use App\Support\AgeGate;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class AgeGateController extends Controller
{
    public function show(): Response
    {
        return Inertia::render('Gate', [
            'backUrl' => '/',
        ]);
    }

    public function confirm(Request $request): RedirectResponse
    {
        AgeGate::confirm();

        return redirect()->intended(route('gallery'));
    }

    public function leave(): RedirectResponse
    {
        AgeGate::forget();

        return redirect('https://www.google.com');
    }
}
