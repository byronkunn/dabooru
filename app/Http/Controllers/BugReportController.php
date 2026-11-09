<?php

namespace App\Http\Controllers;

use App\Models\BugReport;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class BugReportController extends Controller
{
    /** The form. Open to signed-out visitors too, since some bugs only show there. */
    public function show(Request $request): Response
    {
        return Inertia::render('Bug/Index', [
            'kinds' => BugReport::KINDS,
            'prefillUrl' => (string) ($request->query('url') ?? ''),
            'recent' => $request->user()
                ? BugReport::where('user_id', $request->user()->id)->latest()->limit(5)
                    ->get(['id', 'kind', 'title', 'status', 'created_at'])
                    ->map(fn (BugReport $r) => [
                        'id' => $r->id,
                        'kind' => $r->kind,
                        'title' => $r->title,
                        'status' => $r->status,
                        'when' => $r->created_at?->diffForHumans(short: true),
                    ])->all()
                : [],
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'kind' => ['required', 'in:'.implode(',', array_keys(BugReport::KINDS))],
            'title' => ['required', 'string', 'min:4', 'max:160'],
            'details' => ['nullable', 'string', 'max:4000'],
            'page_url' => ['nullable', 'string', 'max:500'],
            // captured by the browser; not trusted for anything but context
            'viewport' => ['nullable', 'string', 'max:40'],
        ]);

        BugReport::create([
            'user_id' => $request->user()?->id,
            'kind' => $data['kind'],
            'title' => $data['title'],
            'details' => $data['details'] ?? null,
            'page_url' => $data['page_url'] ?? null,
            'viewport' => $data['viewport'] ?? null,
            'user_agent' => mb_substr((string) $request->userAgent(), 0, 300),
        ]);

        return back()->with('success', 'Thanks — your report reached the moderators.');
    }
}
