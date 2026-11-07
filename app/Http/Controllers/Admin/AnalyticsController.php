<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Report;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Inertia\Inertia;
use Inertia\Response;

class AnalyticsController extends Controller
{
    public function index(Request $request): Response
    {
        $days = $request->query('days') === '30' ? 30 : 7;
        $start = now()->startOfDay()->subDays($days - 1);
        $series = [
            'signups' => ['New accounts', 'users', 'created_at'],
            'uploads' => ['Published works', 'posts', 'created_at'],
            'views' => ['Work views', 'post_views', 'viewed_on'],
            'saves' => ['Saved works', 'bookmarks', 'created_at'],
            'reports' => ['Reports received', 'reports', 'created_at'],
            'resolved' => ['Reports closed', 'reports', 'resolved_at'],
        ];
        $metrics = [];
        foreach ($series as $key => [$label, $table, $column]) {
            $date = $column === 'viewed_on' ? $start->toDateString() : $start;
            $daily = DB::table($table)->where($column, '>=', $date)
                ->when($key === 'uploads', fn ($query) => $query->where('is_draft', false))
                ->selectRaw("DATE({$column}) as day, COUNT(*) as total")
                ->groupByRaw("DATE({$column})")->pluck('total', 'day');
            $values = collect(range(0, $days - 1))->map(fn ($offset) => (int) $daily->get($start->copy()->addDays($offset)->toDateString(), 0));
            $metrics[$key] = ['label' => $label, 'total' => $values->sum(), 'daily' => $values];
        }

        $seconds = 0;
        $closed = 0;
        foreach (Report::where('resolved_at', '>=', $start)->lazyById(500, 'id') as $report) {
            $seconds += max(0, $report->resolved_at->getTimestamp() - $report->created_at->getTimestamp());
            $closed++;
        }

        return Inertia::render('Admin/Analytics', [
            'days' => $days,
            'dates' => collect(range(0, $days - 1))->map(fn ($offset) => $start->copy()->addDays($offset)->toDateString()),
            'metrics' => $metrics,
            'meanResolutionMinutes' => $closed ? (int) round($seconds / $closed / 60) : null,
        ]);
    }
}
