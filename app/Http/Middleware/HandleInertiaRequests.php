<?php

namespace App\Http\Middleware;

use App\Support\AgeGate;
use Illuminate\Http\Request;
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

        return array_merge(parent::share($request), [
            'ageConfirmed' => AgeGate::confirmed(),
            // local development only: the one-click demo switcher
            'demoAccounts' => \Illuminate\Support\Facades\Route::has('demo.login')
                ? \App\Support\DemoAccounts::users()
                : [],
            'auth' => [
                'user' => $user ? [
                    'id' => $user->id,
                    'name' => $user->name,
                    'username' => $user->username,
                    'avatar' => $user->avatarUrl(),
                    'banner' => $user->bannerUrl(),
                    'is_admin' => $user->isAdmin(),
                    'show_explicit' => $user->show_explicit,
                    'email_verified' => (bool) $user->email_verified_at,
                ] : null,
                'unread_notifications' => $user ? \App\Models\AppNotification::where('user_id', $user->id)->whereNull('read_at')->count() : 0,
                'unread_messages' => $user ? (int) \DB::table('messages')
                    ->join('conversation_participants', function ($j) use ($user) {
                        $j->on('conversation_participants.conversation_id', '=', 'messages.conversation_id')
                            ->where('conversation_participants.user_id', $user->id);
                    })
                    ->where('messages.user_id', '!=', $user->id)
                    ->where(function ($q) {
                        $q->whereNull('conversation_participants.last_read_at')
                            ->orWhereColumn('messages.created_at', '>', 'conversation_participants.last_read_at');
                    })
                    ->count() : 0,
            ],
            'flash' => [
                'success' => fn () => $request->session()->pull('success'),
                'error' => fn () => $request->session()->pull('error'),
            ],
        ]);
    }
}
