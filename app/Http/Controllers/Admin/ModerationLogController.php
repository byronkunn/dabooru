<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\DmcaNotice;
use App\Models\ModerationAction;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class ModerationLogController extends Controller
{
    public function index(Request $request): Response
    {
        $actions = ModerationAction::with('moderator')
            ->latest()
            ->paginate(100);

        $dmca = DmcaNotice::with('handler')->latest()->paginate(50, ['*'], 'dmca_page');

        return Inertia::render('Admin/Log', [
            'actions' => collect($actions->items())->map(fn (ModerationAction $a) => [
                'id' => $a->id,
                'action' => $a->action,
                'target_type' => class_basename((string) $a->target_type),
                'target_id' => $a->target_id,
                'reason' => $a->reason,
                'meta' => $a->meta,
                'moderator' => $a->moderator?->username ?? 'system',
                'when' => $a->created_at?->toIso8601String(),
                'when_human' => $a->created_at?->diffForHumans(short: true),
            ])->all(),
            'dmca' => collect($dmca->items())->map(fn (DmcaNotice $n) => [
                'id' => $n->id,
                'reporter_name' => $n->reporter_name,
                'reporter_email' => $n->reporter_email,
                'url' => $n->url,
                'description' => $n->description,
                'status' => $n->status,
                'when_human' => $n->created_at?->diffForHumans(short: true),
                'handler' => $n->handler?->username,
            ])->all(),
            'totalActions' => ModerationAction::count(),
            'openDmca' => DmcaNotice::where('status', 'open')->count(),
        ]);
    }
}
