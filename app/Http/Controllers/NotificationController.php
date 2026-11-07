<?php

namespace App\Http\Controllers;

use App\Models\AppNotification;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class NotificationController extends Controller
{
    public function index(Request $request): Response
    {
        $tab = in_array($request->query('tab'), ['all', 'likes', 'comments', 'follows'], true)
            ? $request->query('tab')
            : 'all';

        $query = AppNotification::where('user_id', $request->user()->id)->with('actor')->latest();

        $query->when($tab !== 'all', function ($q) use ($tab) {
            $q->where('type', rtrim($tab, 's'));
        });

        return Inertia::render('Notifications/Index', [
            'notifications' => $query->take(50)->get()->map(fn (AppNotification $n) => [
                'id' => $n->id,
                'type' => $n->type,
                'read' => (bool) $n->read_at,
                'created_at_human' => $n->created_at?->diffForHumans(short: true),
                'actor' => $n->actor ? [
                    'id' => $n->actor->id,
                    'name' => $n->actor->name,
                    'username' => $n->actor->username,
                    'avatar' => $n->actor->avatarUrl(),
                ] : null,
                'data' => $n->data ?? [],
            ]),
            'tab' => $tab,
        ]);
    }

    public function markAllRead(Request $request): RedirectResponse
    {
        AppNotification::where('user_id', $request->user()->id)->whereNull('read_at')->update(['read_at' => now()]);

        return back()->with('success', 'All caught up.');
    }
}
