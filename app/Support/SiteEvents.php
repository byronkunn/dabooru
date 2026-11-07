<?php

namespace App\Support;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;
use Symfony\Component\Mailer\Exception\TransportExceptionInterface;
use Throwable;

class SiteEvents
{
    public static function exception(Throwable $exception): void
    {
        try {
            $request = app('request');
            $requestId = null;
            $route = null;
            if ($request instanceof Request && ! app()->runningInConsole()) {
                $requestId = $request->attributes->get('site_request_id') ?? (string) Str::uuid();
                $request->attributes->set('site_request_id', $requestId);
                $route = $request->route()?->getName();
                Log::withContext(['request_id' => $requestId]);
            }

            DB::table('site_events')->insert([
                'category' => $exception instanceof TransportExceptionInterface ? 'mail' : 'application',
                'severity' => 'error',
                'exception' => Str::limit(class_basename($exception), 180, ''),
                'route_name' => $route,
                'request_id' => $requestId,
                'created_at' => now(),
            ]);
        } catch (Throwable) {
            // A database or schema error must still reach Laravel's normal logger.
        }
    }
}
