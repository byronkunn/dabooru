<?php

namespace App\Http\Middleware;

use App\Models\AppNotification;
use App\Support\AgeGate;
use App\Support\ChatAccess;
use App\Support\DemoAccounts;
use App\Support\StaffAccess;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;
use Inertia\Middleware;

class HandleInertiaRequests extends Middleware
{
    protected $rootView = 'app';

    public function version(Request $request): ?string
    {
        return parent::version($request);
    }

    /**
     * Props available to every page.
     */
    public function share(Request $request): array
    {
        $user = $request->user();
        $staffPermissions = $user ? StaffAccess::permissions($user) : [];

        return array_merge(parent::share($request), [
            'ageConfirmed' => AgeGate::confirmed(),
            // local development only: the one-click demo switcher
            'demoAccounts' => Route::has('demo.login')
                ? DemoAccounts::users()
                : [],
            'auth' => [
                'user' => $user ? [
                    'id' => $user->id,
                    'name' => $user->name,
                    'username' => $user->username,
                    'avatar' => $user->avatarUrl(),
                    'banner' => $user->bannerUrl(),
                    'is_admin' => $user->isAdmin(),
                    'staff_permissions' => $staffPermissions,
                    'show_explicit' => $user->show_explicit,
                    'email_verified' => (bool) $user->email_verified_at,
                ] : null,
                'unread_notifications' => $user ? AppNotification::where('user_id', $user->id)
                    ->where('type', '!=', 'chat_mention')
                    ->whereNull('read_at')
                    ->when(! in_array('moderator', $staffPermissions, true), fn ($query) => $query->where('type', '!=', 'urgent_report'))
                    ->count() : 0,
                'unread_messages' => fn () => $user ? ChatAccess::unreadCount($user) : 0,
            ],
            'flash' => [
                'success' => fn () => $request->session()->pull('success'),
                'error' => fn () => $request->session()->pull('error'),
            ],
        ]);
    }
}
