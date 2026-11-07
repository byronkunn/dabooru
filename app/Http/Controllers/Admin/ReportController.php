<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\ModerationAction;
use App\Models\Post;
use App\Models\Report;
use App\Models\User;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class ReportController extends Controller
{
    /** The queue: open reports, most-reported targets first. */
    public function index(Request $request): Response
    {
        $status = in_array($request->query('status'), Report::STATUSES, true) ? $request->query('status') : 'open';

        $reports = Report::with(['reporter', 'handler', 'reportable'])
            ->where('status', $status)
            ->latest()
            ->paginate(50)
            ->withQueryString();

        return Inertia::render('Admin/Reports', [
            'status' => $status,
            'reports' => collect($reports->items())->map(fn (Report $r) => $this->row($r))->all(),
            'counts' => [
                'open' => Report::where('status', 'open')->count(),
                'actioned' => Report::where('status', 'actioned')->count(),
                'dismissed' => Report::where('status', 'dismissed')->count(),
            ],
            'reasons' => Report::REASONS,
            'nextPage' => $reports->hasMorePages() ? $reports->url($reports->currentPage() + 1) : null,
        ]);
    }

    /** Remove the reported thing and close the report. */
    public function remove(Request $request, Report $report): \Illuminate\Http\RedirectResponse
    {
        $data = $request->validate([
            'reason' => ['nullable', 'string', 'max:500'],
        ]);

        $target = $report->reportable;
        $meta = ['report_id' => $report->id, 'reason' => $report->reason];

        if ($target instanceof Post) {
            $meta['title'] = $target->title;
            $meta['user_id'] = $target->user_id;
            $target->delete();           // soft delete: recoverable
        } elseif ($target instanceof \App\Models\Comment) {
            $meta['body'] = $target->body;
            $target->delete();
            $target->post?->decrement('comments_count');
        } elseif ($target instanceof User) {
            $target->forceFill(['banned_at' => now(), 'banned_reason' => $data['reason'] ?? null])->save();
            $meta['username'] = $target->username;
        } elseif ($target instanceof \App\Models\Message) {
            $target->delete();
        }

        $report->forceFill([
            'status' => 'actioned',
            'handled_by' => $request->user()->id,
            'resolved_at' => now(),
        ])->save();

        $action = match (true) {
            $target instanceof User => 'ban_user',
            $target instanceof Post => 'remove_post',
            $target instanceof \App\Models\Comment => 'remove_comment',
            $target instanceof \App\Models\Message => 'remove_message',
            default => 'remove_content',
        };

        ModerationAction::record(
            $request->user(),
            $action,
            $report->reportable_type,
            $report->reportable_id,
            $report->id,
            $data['reason'] ?? null,
            $meta,
        );

        return back()->with('success', 'Removed and report closed.');
    }

    public function dismiss(Request $request, Report $report): \Illuminate\Http\RedirectResponse
    {
        $data = $request->validate(['reason' => ['nullable', 'string', 'max:500']]);

        $report->forceFill([
            'status' => 'dismissed',
            'handled_by' => $request->user()->id,
            'resolved_at' => now(),
        ])->save();

        ModerationAction::record($request->user(), 'dismiss_report', $report->reportable_type, $report->reportable_id, $report->id, $data['reason'] ?? null);

        return back()->with('success', 'Report dismissed.');
    }

    private function row(Report $r): array
    {
        $target = $r->reportable;
        $preview = null;
        $image = null;
        $author = null;
        $url = null;

        if ($target instanceof Post) {
            $preview = $target->title ?: mb_strimwidth((string) $target->caption, 0, 80, '…');
            $image = $target->cover()?->thumbUrl();
            $author = $target->user ? ['name' => $target->user->name, 'username' => $target->user->username] : null;
            $url = route('posts.show', $target->id, false);
        } elseif ($target instanceof \App\Models\Comment) {
            $preview = mb_strimwidth((string) $target->body, 0, 100, '…');
            $author = ['name' => $target->user->name, 'username' => $target->user->username];
            $url = route('posts.show', $target->post_id, false);
        } elseif ($target instanceof User) {
            $preview = $target->name.' @'.$target->username;
            $image = $target->avatarUrl();
            $url = route('profile.show', $target->username, false);
        } elseif ($target instanceof \App\Models\Message) {
            $preview = mb_strimwidth((string) $target->body, 0, 100, '…');
        }

        return [
            'id' => $r->id,
            'reason' => $r->reason,
            'reason_label' => Report::REASONS[$r->reason] ?? $r->reason,
            'details' => $r->details,
            'status' => $r->status,
            'created_at_human' => $r->created_at?->diffForHumans(short: true),
            'reportable_type' => class_basename((string) $r->reportable_type),
            'reportable_id' => $r->reportable_id,
            'preview' => $preview,
            'image' => $image,
            'author' => $author,
            'url' => $url,
            'reporter' => $r->reporter?->username,
            'handler' => $r->handler?->username,
            'resolved_at_human' => $r->resolved_at?->diffForHumans(short: true),
        ];
    }
}
