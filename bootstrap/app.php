<?php

use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;

return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        web: __DIR__.'/../routes/web.php',
        commands: __DIR__.'/../routes/console.php',
        health: '/up',
    )
    ->withMiddleware(function (Middleware $middleware): void {
        $middleware->alias([
            'admin' => \App\Http\Middleware\EnsureAdmin::class,
        ]);

        $middleware->web(append: [
            \App\Http\Middleware\HandleInertiaRequests::class,
            \Illuminate\Http\Middleware\AddLinkHeadersForPreloadedAssets::class,
            \App\Http\Middleware\EnsureAgeConfirmed::class,
        ]);
    })
    ->withExceptions(function (Exceptions $exceptions): void {
        $exceptions->report(fn (\Throwable $error) => \App\Support\SiteEvents::exception($error));

        // Render HTTP errors inside the site's own layout instead of the bare framework page.
        $exceptions->respond(function (\Symfony\Component\HttpFoundation\Response $response, \Throwable $error, \Illuminate\Http\Request $request) {
            $status = $response->getStatusCode();
            $branded = in_array($status, [403, 404, 429, 503], true) || ($status === 500 && ! config('app.debug'));
            if ($branded && ! $request->expectsJson()) {
                return \Inertia\Inertia::render('Error', ['status' => $status])
                    ->toResponse($request)->setStatusCode($status);
            }
            if ($status === 419 && $request->header('X-Inertia')) {
                return back()->with('error', 'Your session expired. Please try again.');
            }

            return $response;
        });
    })->create();
