<?php

namespace App\Http\Middleware;

use App\Support\StaffAccess;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class EnsureAdmin
{
    public function handle(Request $request, Closure $next, string $permission = 'admin'): Response
    {
        $user = $request->user();

        abort_unless($user && in_array($permission, StaffAccess::permissions($user), true), 403, 'Your role does not have access to this area.');

        return $next($request);
    }
}
