<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\ModerationAction;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class ModerationLogController extends Controller
{
    public function index(Request $request): Response
    {
        $filters = $request->validate([
            'action' => ['nullable', 'string', 'max:80'],
            'from' => ['nullable', 'date_format:Y-m-d'],
            'to' => ['nullable', 'date_format:Y-m-d'],
        ]);
        $actions = ModerationAction::with('moderator:id,username')
            ->when(isset($filters['action']), fn ($q) => $q->where('action', $filters['action']))
            ->when(isset($filters['from']), fn ($q) => $q->whereDate('created_at', '>=', $filters['from']))
            ->when(isset($filters['to']), fn ($q) => $q->whereDate('created_at', '<=', $filters['to']))
            ->latest()->orderByDesc('id')->paginate(50)->withQueryString();

        return Inertia::render('Admin/Log', [
            'actions' => $actions->getCollection()->map(fn (ModerationAction $action) => [
                'id' => $action->id,
                'action' => $action->action,
                'target_type' => class_basename((string) $action->target_type),
                'target_id' => $action->target_id,
                'reason' => $action->reason,
                'role_change' => $action->action === 'change_staff_roles' ? $action->meta : null,
                'message_context' => str_starts_with($action->action, 'search_messages') || str_starts_with($action->action, 'view_messages') || str_starts_with($action->action, 'view_message_attachment') || str_starts_with($action->action, 'remove_message') ? $action->meta : null,
                'moderator' => $action->moderator?->username,
                'when' => $action->created_at->toIso8601String(),
            ]),
            'filters' => $filters,
            'actionOptions' => ModerationAction::distinct()->orderBy('action')->pluck('action'),
            'totalActions' => $actions->total(),
            'previousPage' => $actions->previousPageUrl(),
            'nextPage' => $actions->nextPageUrl(),
        ]);
    }
}
