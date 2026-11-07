<?php

namespace App\Http\Controllers;

use App\Models\DmcaNotice;
use App\Models\ModerationAction;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class DmcaController extends Controller
{
    public function show(): Response
    {
        return Inertia::render('Legal/Dmca', [
            'submitted' => session('dmca_submitted', false),
        ]);
    }

    /**
     * Takes a notice. The form asks for the two statements the statute
     * requires (good faith + acknowledgement of perjury liability); without
     * them a notice is not a valid DMCA takedown demand and is not accepted.
     */
    public function submit(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'reporter_name' => ['required', 'string', 'max:120'],
            'reporter_email' => ['required', 'email', 'max:190'],
            'rights_holder' => ['nullable', 'string', 'max:190'],
            'url' => ['required', 'url', 'max:500'],
            'description' => ['required', 'string', 'min:20', 'max:5000'],
            'good_faith' => ['accepted'],
            'penalty_ack' => ['accepted'],
        ], [
            'good_faith.accepted' => 'You must confirm you have a good-faith belief the use is unauthorised.',
            'penalty_ack.accepted' => 'You must acknowledge that a false statement carries liability.',
            'description.min' => 'Please describe the work and where it is being used.',
        ]);

        $notice = DmcaNotice::create([
            'reporter_name' => $data['reporter_name'],
            'reporter_email' => $data['reporter_email'],
            'rights_holder' => $data['rights_holder'] ?? null,
            'url' => $data['url'],
            'description' => $data['description'],
            'good_faith' => true,
            'penalty_ack' => true,
        ]);

        ModerationAction::record(null, 'dmca_notice', DmcaNotice::class, $notice->id, null, 'DMCA notice received', [
            'url' => $notice->url,
        ]);

        return redirect()->route('dmca.submitted');
    }

    public function submitted(): Response
    {
        return Inertia::render('Legal/DmcaSent');
    }
}
