<?php

namespace App\Http\Controllers;

use App\Models\AppNotification;
use App\Models\Comment;
use App\Models\Message;
use App\Models\Post;
use App\Models\Report;
use App\Models\User;
use App\Notifications\UrgentReportAlert;
use App\Support\ChatAccess;
use App\Support\StaffAccess;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Notification;
use Inertia\Inertia;

class ReportController extends Controller
{
    /**
     * The reporter's own view of what happened to their reports.
     *
     * Three reader-facing states, derived from the moderation queue:
     *   Received     filed, nobody has picked it up yet
     *   Under review a moderator has claimed it
     *   Closed       a decision was reached (actioned or dismissed)
     */
    public function index(Request $request)
    {
        $viewer = $request->user();

        $reports = Report::query()
            ->where('reporter_id', $viewer->id)
            ->with(['assignee:id,username,name', 'handler:id,username,name'])
            ->latest()
            ->paginate(30)
            ->withQueryString()
            ->through(function (Report $report) {
                $state = match (true) {
                    $report->status === 'open' => $report->assigned_to ? 'under_review' : 'received',
                    default => 'closed',
                };

                return [
                    'id' => $report->id,
                    'reason' => $report->reason,
                    'reason_label' => Report::REASONS[$report->reason] ?? $report->reason,
                    'state' => $state,
                    'state_label' => self::STATES[$state],
                    'target_type' => class_basename($report->reportable_type),
                    'target_id' => $report->reportable_id,
                    'target_label' => $this->targetLabel($report),
                    'outcome' => $report->status === 'actioned' ? 'Action taken' : ($report->status === 'dismissed' ? 'No action needed' : null),
                    'created_at_human' => $report->created_at?->diffForHumans(short: true),
                    'closed_at_human' => $report->resolved_at?->diffForHumans(short: true),
                ];
            });

        return Inertia::render('Reports/Index', [
            'reports' => $reports,
            'states' => self::STATES,
            'summary' => [
                'received' => Report::where('reporter_id', $viewer->id)->where('status', 'open')->whereNull('assigned_to')->count(),
                'under_review' => Report::where('reporter_id', $viewer->id)->where('status', 'open')->whereNotNull('assigned_to')->count(),
                'closed' => Report::where('reporter_id', $viewer->id)->whereIn('status', ['actioned', 'dismissed'])->count(),
            ],
        ]);
    }

    /** What a reader sees for each internal report state. */
    public const STATES = [
        'received' => 'Received',
        'under_review' => 'Under review',
        'closed' => 'Closed',
    ];

    /** A short, safe description of the reported item for the reporter's own list. */
    private function targetLabel(Report $report): string
    {
        return match (class_basename($report->reportable_type)) {
            'Post' => 'Work #'.$report->reportable_id,
            'Comment' => 'Comment #'.$report->reportable_id,
            'Message' => 'Message',
            'User' => 'Account #'.$report->reportable_id,
            default => 'Item #'.$report->reportable_id,
        };
    }

    public function store(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'reportable_type' => ['required', 'in:post,comment,user,message'],
            'reportable_id' => ['required', 'integer'],
            'reason' => ['required', 'in:minor,non_consensual,spam,copyright,other'],
            'details' => ['nullable', 'string', 'max:1000'],
        ]);

        $map = [
            'post' => Post::class,
            'comment' => Comment::class,
            'user' => User::class,
            'message' => Message::class,
        ];

        $target = $map[$data['reportable_type']]::findOrFail($data['reportable_id']);
        if ($target instanceof Message) {
            $member = ChatAccess::membership($target->conversation, $request->user());
            abort_unless($member && ChatAccess::readable($target->conversation, $request->user())
                ->whereKey($target->id)->exists(), 404);
        }

        $report = DB::transaction(function () use ($request, $data, $target) {
            $evidence = [];
            if ($target instanceof Message) {
                $target = Message::lockForUpdate()->findOrFail($target->id);
                abort_if($target->removed_at || ($target->expires_at && $target->expires_at->isPast()), 404);
                $evidence = [
                    'message_evidence' => $target->body ?? $target->interactive['title'] ?? '',
                    'evidence_media_id' => $target->media_id,
                    'message_context' => Report::sharedWorkContext($target),
                ];
            }
            $report = Report::create($evidence + [
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
