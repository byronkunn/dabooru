<?php

namespace App\Support;

use Illuminate\Support\Facades\Cache;

class VerifiedGooglebot
{
    public static function check(?string $userAgent, ?string $ip): bool
    {
        if (! $ip || ! filter_var($ip, FILTER_VALIDATE_IP) || ! $userAgent || ! str_contains($userAgent, 'Googlebot')) {
            return false;
        }

        return Cache::remember('verified-googlebot:'.hash('sha256', $ip), now()->addHours(6), function () use ($ip): bool {
            $hostname = @gethostbyaddr($ip);
            if (! is_string($hostname) || $hostname === $ip) {
                return false;
            }

            $hostname = strtolower(rtrim($hostname, '.'));
            $isGoogleHostname = str_ends_with($hostname, '.googlebot.com')
                || str_ends_with($hostname, '.google.com')
                || $hostname === 'googlebot.com'
                || $hostname === 'google.com';

            if (! $isGoogleHostname) {
                return false;
            }

            $records = @dns_get_record($hostname, DNS_A | DNS_AAAA);
            foreach (is_array($records) ? $records : [] as $record) {
                $resolvedIp = $record['ip'] ?? $record['ipv6'] ?? null;
                $resolvedPacked = $resolvedIp ? @inet_pton((string) $resolvedIp) : false;
                $clientPacked = @inet_pton($ip);
                if ($resolvedPacked !== false && $clientPacked !== false && hash_equals($clientPacked, $resolvedPacked)) {
                    return true;
                }
            }

            return false;
        });
    }
}
