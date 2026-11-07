<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Media;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;
use Inertia\Inertia;
use Inertia\Response;

class SiteLogController extends Controller
{
    public function index(Request $request): Response
    {
        $filters = $request->validate([
            'category' => ['nullable', Rule::in(['application', 'mail'])],
            'severity' => ['nullable', Rule::in(['error'])],
            'request_id' => ['nullable', 'uuid'],
            'from' => ['nullable', 'date_format:Y-m-d'],
            'to' => ['nullable', 'date_format:Y-m-d'],
        ]);
        $events = DB::table('site_events')->where('created_at', '>=', now()->subDays(30))
            ->when(isset($filters['category']), fn ($q) => $q->where('category', $filters['category']))
            ->when(isset($filters['severity']), fn ($q) => $q->where('severity', $filters['severity']))
            ->when(isset($filters['request_id']), fn ($q) => $q->where('request_id', $filters['request_id']))
            ->when(isset($filters['from']), fn ($q) => $q->whereDate('created_at', '>=', $filters['from']))
            ->when(isset($filters['to']), fn ($q) => $q->whereDate('created_at', '<=', $filters['to']))
            ->latest()->orderByDesc('id')->simplePaginate(50)->withQueryString();

        return Inertia::render('Admin/SiteLogs', [
            'filters' => $filters,
            'events' => $events->items(),
            'previousPage' => $events->previousPageUrl(),
            'nextPage' => $events->nextPageUrl(),
            'health' => [
                'failedJobs' => DB::table('failed_jobs')->count(),
                'queuedJobs' => DB::table('jobs')->count(),
                'olderVideos' => Media::where('kind', 'video')->where('status', 'processing')->where('created_at', '<=', now()->subHour())->count(),
            ],
            'failedJobs' => DB::table('failed_jobs')->latest('failed_at')->limit(15)->get(['uuid', 'failed_at']),
        ]);
    }
}
