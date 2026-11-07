<?php

namespace App\Http\Controllers;

use App\Models\Report;
use App\Models\AppNotification;
use App\Notifications\UrgentReportAlert;
use App\Support\StaffAccess;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Notification;
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

        $target = $map[$data['reportable_type']]::findOrFail($data['reportable_id']);
        if ($target instanceof \App\Models\Message) {
            abort_unless($target->conversation->participants()->whereKey($request->user()->id)->exists(), 404);
        }

        $report = DB::transaction(function () use ($request, $data, $target) {
            $report = Report::create([
                'reporter_id' => $request->user()->id,
                'reportable_type' => $target::class,
                'reportable_id' => $target->id,
                'reason' => $data['reason'],
                'details' => $data['details'] ?? null,
            ]);

            if (in_array($report->reason, Report::URGENT_REASONS, true)) {
                StaffAccess::moderators()->select('id')->chunkById(100, function ($staff) use ($report) {
                    $now = now();
                    AppNotification::insert($staff->map(fn ($recipient) => [
                        'user_id' => $recipient->id,
                        'type' => 'urgent_report',
                        'subject_type' => Report::class,
                        'subject_id' => $report->id,
                        'data' => json_encode(['report_id' => $report->id]),
                        'created_at' => $now,
                        'updated_at' => $now,
                    ])->all());
                });
            }

            return $report;
        });

        $email = config('admin.urgent_report_email');
        if (in_array($report->reason, Report::URGENT_REASONS, true) && is_string($email) && filter_var($email, FILTER_VALIDATE_EMAIL)) {
            rescue(fn () => Notification::route('mail', $email)->notify(new UrgentReportAlert($report->id)));
        }

        return back()->with('success', 'Report received. Thank you.');
    }
}
