<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    private const ADDRESSES = [
        'bitcoin' => 'bc1qvet3xggfmft7axrnh9tdz8qfg8l0qph2uxg0m3',
        'ethereum' => '0x051A40fab44317a625734E854db7f2b90270A098',
        'solana' => 'BmqyLY6FNWLFzbZsQPRRintSgmRRFuFcmL71kXhBUL5V',
    ];

    public function up(): void
    {
        $ownerId = (int) config('admin.owner_id');
        if (! $ownerId || ! DB::table('users')->where('id', $ownerId)->exists()) {
            $ownerId = (int) DB::table('users')->where('is_admin', true)->orderBy('id')->value('id');
        }
        if (! $ownerId) {
            return;
        }

        $setting = DB::table('settings')->where('user_id', $ownerId)->where('key', 'tip_addresses')->first();
        $addresses = $setting ? json_decode($setting->value, true) : [];
        $addresses = is_array($addresses) ? $addresses : [];
        $addresses = array_merge($addresses, self::ADDRESSES);

        DB::table('settings')->updateOrInsert(
            ['user_id' => $ownerId, 'key' => 'tip_addresses'],
            ['value' => json_encode($addresses)],
        );
    }

    public function down(): void
    {
        $ownerId = (int) config('admin.owner_id');
        if (! $ownerId) {
            $ownerId = (int) DB::table('users')->where('is_admin', true)->orderBy('id')->value('id');
        }
        if (! $ownerId) {
            return;
        }

        $setting = DB::table('settings')->where('user_id', $ownerId)->where('key', 'tip_addresses')->first();
        $addresses = $setting ? json_decode($setting->value, true) : [];
        if (! is_array($addresses)) {
            return;
        }

        foreach (self::ADDRESSES as $network => $address) {
            if (($addresses[$network] ?? null) === $address) {
                unset($addresses[$network]);
            }
        }

        if ($addresses === []) {
            DB::table('settings')->where('user_id', $ownerId)->where('key', 'tip_addresses')->delete();
        } else {
            DB::table('settings')->where('user_id', $ownerId)->where('key', 'tip_addresses')
                ->update(['value' => json_encode($addresses)]);
        }
    }
};
