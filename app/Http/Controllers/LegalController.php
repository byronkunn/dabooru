<?php

namespace App\Http\Controllers;

use Inertia\Inertia;
use Inertia\Response;

/**
 * The pages registration already makes people agree to. Content lives in
 * resources/js/Pages/Legal so it can be edited without touching PHP.
 */
class LegalController extends Controller
{
    public function terms(): Response
    {
        return Inertia::render('Legal/Terms', ['updated' => '2026-11-06']);
    }

    public function privacy(): Response
    {
        return Inertia::render('Legal/Privacy', ['updated' => '2026-11-06']);
    }

    public function policy(): Response
    {
        return Inertia::render('Legal/ContentPolicy', ['updated' => '2026-11-06']);
    }
}
