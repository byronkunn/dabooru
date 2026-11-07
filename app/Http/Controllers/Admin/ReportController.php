<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\ModerationAction;
use App\Models\Post;
use App\Support\Moderation;
use App\Models\Report;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;
use Inertia\Inertia;
use Inertia\Response;

class ReportController extends Controller
{
    /** Open reports put urgent items first, oldest first within each priority. */
    public function index(Request $request): Response
    {
        $status = in_array($request->query('status'), Report::STATUSES, true) ? $request->query('status') : 'open';

        $filters = $request->validate([
            'priority' => ['nullable', Rule::in(['urgent'])],
            'assignment' => ['nullable', Rule::in(['mine', 'unassigned'])],
            'target_type' => ['nullable', Rule::in(['Post', 'Comment', 'User', 'Message'])],
            'target_id' => ['nullable', 'integer', 'min:1'],
            'report' => ['nullable', 'integer', 'min:1'],
        ]);
        $related = Report::from('reports as related')
            ->whereColumn('related.reportable_type', 'reports.reportable_type')
            ->whereColumn('related.reportable_id', 'reports.reportable_id')
            ->where('related.status', 'open');

        $reports = Report::query()
            ->with(['reporter', 'handler', 'assignee', 'reportable' => fn ($relation) => $relation->withTrashed()])
            ->addSelect([
                'related_open' => (clone $related)->selectRaw('COUNT(*)'),
                'related_urgent' => (clone $related)->whereIn('related.reason', Report::URGENT_REASONS)->selectRaw('COUNT(*)'),
            ])
            ->when(isset($filters['report']), fn ($q) => $q->whereKey($filters['report']), fn ($q) => $q->where('status', $status))
            ->when(isset($filters['priority']), fn ($q) => $q->whereIn('reason', Report::URGENT_REASONS))
            ->when(($filters['assignment'] ?? null) === 'mine', fn ($q) => $q->where('assigned_to', $request->user()->id))
            ->when(($filters['assignment'] ?? null) === 'unassigned', fn ($q) => $q->whereNull('assigned_to'))
            ->when(isset($filters['target_type'], $filters['target_id']), fn ($q) => $q
                ->where('reportable_type', 'App\\Models\\'.$filters['target_type'])
                ->where('reportable_id', $filters['target_id']))
            ->when($status === 'open', fn ($q) => $q
                ->orderByRaw('CASE WHEN reason IN (?, ?) THEN 0 ELSE 1 END', Report::URGENT_REASONS)
                ->oldest()->orderBy('id'), fn ($q) => $q->latest('resolved_at')->orderByDesc('id'))
            ->paginate(50)->withQueryString();

        return Inertia::render('Admin/Reports', [
            'status' => $status,
            'reports' => collect($reports->items())->map(fn (Report $r) => $this->row($r))->all(),
            'counts' => [
                'open' => Report::where('status', 'open')->count(),
                'actioned' => Report::where('status', 'actioned')->count(),
                'dismissed' => Report::where('status', 'dismissed')->count(),
            ],
            'filters' => $filters,
            'urgentCount' => Report::open()->whereIn('reason', Report::URGENT_REASONS)->count(),
            'previousPage' => $reports->previousPageUrl(),
            'nextPage' => $reports->hasMorePages() ? $reports->url($reports->currentPage() + 1) : null,
        ]);
    }

    /** Remove the reported thing and close the report. */
    public function remove(Request $request, Report $report): \Illuminate\Http\RedirectResponse
    {
        $data = $request->validate([
            'reason' => ['nullable', 'string', 'max:500'],
        ]);

        DB::transaction(function () use ($request, $report, $data) {
            $report = $this->openReport($report);
            $target = $report->reportable()->lockForUpdate()->first();
            abort_unless($target, 422, 'This item is unavailable. Dismiss the report with a note.');

            $action = match (true) {
                $target instanceof User => 'ban_user',
                $target instanceof Post => 'remove_post',
                $target instanceof \App\Models\Comment => 'remove_comment',
                $target instanceof \App\Models\Message => 'remove_message',
            };

            if ($target instanceof Post) {
                Moderation::hide($target);
            } elseif ($target instanceof User) {
                Moderation::ban($request->user(), $target, $data['reason'] ?? null);
            } else {
                $target->delete();
                if ($target instanceof \App\Models\Comment) {
                    $target->post?->decrement('comments_count');
                }
            }

            $this->resolve($report, $request->user(), 'actioned', $action, $data['reason'] ?? null);
        });

        return back()->with('success', 'Removed and report closed.');
    }

    public function dismiss(Request $request, Report $report): \Illuminate\Http\RedirectResponse
    {
        $data = $request->validate(['reason' => ['nullable', 'string', 'max:500']]);

        DB::transaction(fn () => $this->resolve(
            $this->openReport($report), $request->user(), 'dismissed', 'dismiss_report', $data['reason'] ?? null
        ));

        return back()->with('success', 'Report dismissed.');
    }

    public function assign(Request $request, Report $report): \Illuminate\Http\RedirectResponse
    {
        $data = $request->validate(['claim' => ['required', 'boolean']]);

        DB::transaction(function () use ($request, $report, $data) {
            $report = $this->openReport($report);
            $actor = $request->user();
            abort_if($report->assigned_to && $report->assigned_to !== $actor->id && ! $actor->isAdmin(), 409, 'Another moderator has claimed this report.');
            $assignee = $data['claim'] ? $actor->id : null;
            if ($assignee !== $report->assigned_to) {
                $report->forceFill(['assigned_to' => $assignee])->save();
                ModerationAction::record($actor, $assignee ? 'claim_report' : 'release_report', Report::class, $report->id, $report->id);
            }
        });

        return back();
    }

    public function rating(Request $request, Report $report): \Illuminate\Http\RedirectResponse
    {
        $data = $request->validate(['rating' => ['required', Rule::in(User::RATINGS)], 'reason' => ['required', 'string', 'max:500']]);
        DB::transaction(function () use ($request, $report, $data) {
            $report = $this->openReport($report);
            abort_unless($report->reportable_type === Post::class, 422);
            $post = Post::lockForUpdate()->findOrFail($report->reportable_id);
            $hasUrgentReport = Report::open()->where('reportable_type', Post::class)
                ->where('reportable_id', $post->id)->whereIn('reason', Report::URGENT_REASONS)->exists();
            abort_if($hasUrgentReport, 422, 'Urgent safety reports need a separate decision.');
            $post->update(['rating' => $data['rating']]);
            $post->chapter()->update(['rating' => $data['rating']]);
            $this->resolve($report, $request->user(), 'actioned', 'change_rating', $data['reason']);
        });

        return back()->with('success', 'Work rating updated and report closed.');
    }

    private function openReport(Report $report): Report
    {
        $report = Report::lockForUpdate()->findOrFail($report->id);
        abort_unless($report->status === 'open', 409, 'This report is already closed.');

        return $report;
    }

    private function resolve(Report $report, User $actor, string $status, string $action, ?string $reason): void
    {
        $report->forceFill(['status' => $status, 'handled_by' => $actor->id, 'resolved_at' => now()])->save();
        ModerationAction::record($actor, $action, $report->reportable_type, $report->reportable_id, $report->id, $reason);
    }

    private function row(Report $r): array
    {
        $target = $r->reportable;
        $sensitive = in_array($r->reason, Report::URGENT_REASONS, true) || (int) $r->related_urgent > 0;
        $preview = null;
        $image = null;
        $author = null;
        $url = null;

        if ($target instanceof Post) {
            $preview = $target->title ?: mb_strimwidth((string) $target->caption, 0, 80, '…');
            $image = $sensitive ? null : $target->cover()?->thumbUrl();
            $author = $target->user ? ['name' => $target->user->name, 'username' => $target->user->username] : null;
            $url = $target->trashed() ? null : route('posts.show', $target->id, false);
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
            'urgent' => in_array($r->reason, Report::URGENT_REASONS, true),
            'assigned_to' => $r->assigned_to,
            'assignee' => $r->assignee?->username,
            'related_open' => (int) $r->related_open,
            'available' => $target !== null && (! $target instanceof Post && ! $target instanceof \App\Models\Comment || ! $target->trashed()),
            'rating' => $target instanceof Post && ! $target->trashed() ? $target->rating : null,
            'can_restore' => $target instanceof Post && $target->trashed() && $target->moderation_hidden_at !== null,
            'reason_label' => Report::REASONS[$r->reason] ?? $r->reason,
            'details' => $r->details,
            'status' => $r->status,
            'created_at_human' => $r->created_at?->diffForHumans(short: true),
            'reportable_type' => class_basename((string) $r->reportable_type),
            'reportable_id' => $r->reportable_id,
            'preview' => $preview,
            'image' => $sensitive ? null : $image,
            'author' => $author,
            'url' => $url,
            'sensitive' => $sensitive,
            'reporter' => $r->reporter?->username,
            'handler' => $r->handler?->username,
            'resolved_at_human' => $r->resolved_at?->diffForHumans(short: true),
        ];
    }
}
