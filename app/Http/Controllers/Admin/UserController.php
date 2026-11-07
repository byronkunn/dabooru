<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\ModerationAction;
use App\Models\User;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class UserController extends Controller
{
    public function index(Request $request): Response
    {
        $term = trim((string) $request->query('q'));
        $filter = in_array($request->query('filter'), ['all', 'banned', 'unsorted'], true)
            ? $request->query('filter')
            : 'all';

        $users = User::query()
            ->when($term !== '', fn ($q) => $q->where(fn ($w) => $w
                ->where('username', 'like', "%{$term}%")
                ->orWhere('name', 'like', "%{$term}%")
                ->orWhere('email', 'like', "%{$term}%")))
            ->when($filter === 'banned', fn ($q) => $q->whereNotNull('banned_at'))
            ->when($filter === 'unsorted', fn ($q) => $q->where('is_unsorted', true))
            ->withCount('posts')
            ->orderByDesc('banned_at')
            ->orderBy('name')
            ->paginate(50)
            ->withQueryString();

        return Inertia::render('Admin/Users', [
            'users' => collect($users->items())->map(fn (User $u) => [
                'id' => $u->id,
                'name' => $u->name,
                'username' => $u->username,
                'email' => $u->email,
                'avatar' => $u->avatarUrl(),
                'posts' => $u->posts_count,
                'is_admin' => $u->isAdmin(),
                'is_unsorted' => $u->isUnsorted(),
                'banned_at' => $u->banned_at?->toIso8601String(),
                'banned_reason' => $u->banned_reason,
                'verified' => (bool) $u->email_verified_at,
            ])->all(),
            'q' => $term,
            'filter' => $filter,
            'nextPage' => $users->hasMorePages() ? $users->url($users->currentPage() + 1) : null,
        ]);
    }

    public function ban(Request $request, User $user): RedirectResponse
    {
        $data = $request->validate(['reason' => ['required', 'string', 'max:500']]);

        abort_if($user->id === $request->user()->id, 422, 'You cannot ban yourself.');
        abort_if($user->isAdmin(), 422, 'Remove admin rights before banning an account.');

        $user->forceFill(['banned_at' => now(), 'banned_reason' => $data['reason']])->save();

        ModerationAction::record($request->user(), 'ban_user', User::class, $user->id, null, $data['reason'], [
            'username' => $user->username,
        ]);

        return back()->with('success', "{$user->name} banned.");
    }

    public function unban(Request $request, User $user): RedirectResponse
    {
        $user->forceFill(['banned_at' => null, 'banned_reason' => null])->save();

        ModerationAction::record($request->user(), 'unban_user', User::class, $user->id, null, null, [
            'username' => $user->username,
        ]);

        return back()->with('success', "{$user->name} unbanned.");
    }
}
