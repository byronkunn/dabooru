<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\ModerationAction;
use App\Models\User;
use App\Support\Moderation;
use App\Support\StaffAccess;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;
use Inertia\Inertia;
use Inertia\Response;

class UserController extends Controller
{
    public function index(Request $request): Response
    {
        $term = trim((string) $request->query('q'));
        $filter = in_array($request->query('filter'), ['all', 'staff', 'banned', 'unsorted'], true)
            ? $request->query('filter')
            : 'all';

        $users = User::query()
            ->when($term !== '', fn ($q) => $q->where(fn ($w) => $w
                ->where('username', 'like', "%{$term}%")
                ->orWhere('name', 'like', "%{$term}%")
                ->orWhere('email', 'like', "%{$term}%")))
            ->when($filter === 'staff', fn ($q) => $q->where(fn ($q) => $q->where('is_admin', true)->orWhereJsonLength('staff_roles', '>', 0)))
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
                'roles' => $u->staff_roles ?? [],
                'is_owner' => StaffAccess::owner($u),
                'is_unsorted' => $u->isUnsorted(),
                'banned_at' => $u->banned_at?->toIso8601String(),
                'banned_reason' => $u->banned_reason,
                'verified' => (bool) $u->email_verified_at,
            ])->all(),
            'q' => $term,
            'filter' => $filter,
            'roleOptions' => StaffAccess::ROLES,
            'canManageAdmins' => StaffAccess::owner($request->user()),
            'previousPage' => $users->previousPageUrl(),
            'nextPage' => $users->hasMorePages() ? $users->url($users->currentPage() + 1) : null,
        ]);
    }

    public function ban(Request $request, User $user): RedirectResponse
    {
        $data = $request->validate(['reason' => ['required', 'string', 'max:500']]);

        DB::transaction(function () use ($request, $user, $data) {
            $target = User::lockForUpdate()->findOrFail($user->id);
            Moderation::ban($request->user(), $target, $data['reason']);
            ModerationAction::record($request->user(), 'ban_user', User::class, $target->id, null, $data['reason']);
        });

        return back()->with('success', "{$user->name} banned.");
    }

    public function unban(Request $request, User $user): RedirectResponse
    {
        DB::transaction(function () use ($request, $user) {
            $target = User::lockForUpdate()->findOrFail($user->id);
            if ($target->isBanned()) {
                $target->forceFill(['banned_at' => null, 'banned_reason' => null])->save();
                ModerationAction::record($request->user(), 'unban_user', User::class, $target->id, null);
            }
        });

        return back()->with('success', "{$user->name} unbanned.");
    }

    public function roles(Request $request, User $user): RedirectResponse
    {
        $actor = $request->user();
        $data = $request->validate([
            'roles' => ['present', 'array', 'max:4'],
            'roles.*' => ['required', 'distinct', Rule::in(array_keys(StaffAccess::ROLES))],
            'administrator' => ['sometimes', 'boolean'],
            'reason' => ['required', 'string', 'max:500'],
        ]);

        DB::transaction(function () use ($actor, $user, $data) {
            $target = User::lockForUpdate()->findOrFail($user->id);
            abort_if($target->id === $actor->id || StaffAccess::owner($target), 422, 'This account cannot be edited here.');
            $owner = StaffAccess::owner($actor);
            abort_if(! $owner && ($target->isAdmin() || array_key_exists('administrator', $data)), 403);
            $before = ['roles' => $target->staff_roles ?? [], 'administrator' => $target->isAdmin()];
            $after = ['roles' => array_values($data['roles']), 'administrator' => (bool) ($data['administrator'] ?? $before['administrator'])];
            sort($after['roles']);
            abort_if(($target->isBanned() || $target->isUnsorted()) && ($after['roles'] || $after['administrator']), 422, 'This account cannot receive staff access.');
            if ($before !== $after) {
                $target->forceFill(['staff_roles' => $after['roles'], 'is_admin' => $after['administrator']])->save();
                ModerationAction::record($actor, 'change_staff_roles', User::class, $target->id, null, $data['reason'], [
                    'before' => $before, 'after' => $after,
                ]);
            }
        });

        return back()->with('success', 'Staff access updated.');
    }
}
