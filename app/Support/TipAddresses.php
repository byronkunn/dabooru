<?php

namespace App\Support;

use App\Models\Setting;
use App\Models\User;

/**
 * The site's tip addresses.
 *
 * Only the owner (the admin account in config/admin.php) can see the full
 * addresses and edit them. Everyone else gets the network, the label and a
 * "copy" affordance, which is all a tipper needs and keeps the addresses from
 * being scraped off the site by bots.
 */
class TipAddresses
{
    public const KEY = 'tip_addresses';

    /** Networks the site supports, in display order. */
    public const NETWORKS = [
        'monero' => ['label' => 'Monero', 'symbol' => 'XMR', 'hint' => 'Private by default'],
        'bitcoin' => ['label' => 'Bitcoin', 'symbol' => 'BTC', 'hint' => ''],
        'ethereum' => ['label' => 'Ethereum', 'symbol' => 'ETH', 'hint' => 'ERC-20 only'],
        'solana' => ['label' => 'Solana', 'symbol' => 'SOL', 'hint' => ''],
    ];

    /** @return array<string, string> network => address */
    public static function stored(User $owner): array
    {
        $raw = Setting::query()->where('user_id', $owner->id)->where('key', self::KEY)->value('value');
        $decoded = $raw ? json_decode($raw, true) : [];

        return is_array($decoded) ? array_intersect_key($decoded, self::NETWORKS) : [];
    }

    /** Only well-formed, non-empty addresses are kept. */
    public static function save(User $owner, array $addresses): array
    {
        $clean = [];
        foreach (self::NETWORKS as $network => $meta) {
            $value = trim((string) ($addresses[$network] ?? ''));
            if ($value !== '') {
                $clean[$network] = mb_substr($value, 0, 128);
            }
        }

        Setting::query()->updateOrCreate(
            ['user_id' => $owner->id, 'key' => self::KEY],
            ['value' => json_encode($clean)],
        );

        return $clean;
    }

    /**
     * What a visitor sees: the network list and which ones are set up.
     * Addresses are included only when $includeAddresses is true (the owner).
     */
    public static function publicList(User $owner, bool $includeAddresses = false): array
    {
        $stored = self::stored($owner);

        return collect(self::NETWORKS)->map(fn (array $meta, string $network) => [
            'network' => $network,
            'label' => $meta['label'],
            'symbol' => $meta['symbol'],
            'hint' => $meta['hint'],
            'configured' => isset($stored[$network]),
            'address' => $includeAddresses ? ($stored[$network] ?? null) : null,
        ])->values()->all();
    }

    /** True when at least one address is set, so the UI can hide an empty panel. */
    public static function any(User $owner): bool
    {
        return self::stored($owner) !== [];
    }
}
