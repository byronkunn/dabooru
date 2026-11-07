<?php

namespace App\Http\Controllers;

use App\Models\Report;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

class ReportController extends Controller
{
    public function store(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'reportable_type' => ['required', 'in:post,comment,user,message'],
            'reportable_id' => ['required', 'integer'],
            'reason' => ['required', 'in:minor,non_consensual,spam,copyright,other'],
            'details' => ['nullable', 'string', 'max:1000'],
        ]);

        $map = [
            'post' => \App\Models\Post::class,
            'comment' => \App\Models\Comment::class,
            'user' => \App\Models\User::class,
            'message' => \App\Models\Message::class,
        ];

        Report::create([
            'reporter_id' => $request->user()?->id,
            'reportable_type' => $map[$data['reportable_type']],
            'reportable_id' => $data['reportable_id'],
            'reason' => $data['reason'],
            'details' => $data['details'] ?? null,
        ]);

        return back()->with('success', 'Report received. Thank you.');
    }
}
