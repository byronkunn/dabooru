<?php

namespace App\Support;

use App\Models\User;
use Illuminate\Database\Eloquent\Builder;

class StaffAccess
{
    public const ROLES = [
        'moderator' => 'Moderate the public reports queue and reported accounts',
        'curator' => 'Review community edits and manage wiki and tag contributions',
        'analyst' => 'View aggregate site analytics',
        'operator' => 'View site errors and queue health',
    ];

    public static function permissions(User $user): array
    {
        if ($user->isBanned()) {
            return [];
        }

        return $user->isAdmin() ? array_merge(['admin'], array_keys(self::ROLES))
            : array_values(array_intersect(array_keys(self::ROLES), $user->staff_roles ?? []));
    }

    public static function owner(User $user): bool
    {
        return $user->isAdmin() && $user->id === config('admin.owner_id');
    }

    public static function moderators(): Builder
    {
        return User::query()->whereNull('banned_at')->where(fn ($q) => $q
            ->where('is_admin', true)->orWhereJsonContains('staff_roles', 'moderator'));
    }
}
