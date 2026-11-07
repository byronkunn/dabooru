<?php

namespace App\Http\Controllers;

use App\Models\Setting;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Inertia\Inertia;
use Inertia\Response;

class SettingsController extends Controller
{
    private const DEFAULTS = [
        'show_explicit' => true,
        'mature_preview' => false,
        'autoplay_videos' => false,
        'animate_gifs' => true,
        'reduce_motion' => false,
        'reader_mode' => 'page',
        'notification_likes' => true,
        'notification_comments' => true,
        'notification_follows' => true,
        'email_notifications' => false,
        'dm_requests' => true,
        'read_receipts' => true,
    ];

    public function edit(Request $request): Response
    {
        $user = $request->user();

        return Inertia::render('Settings/Index', [
            'user' => [
                'name' => $user->name,
                'username' => $user->username,
                'email' => $user->email,
                'bio' => $user->bio,
                'location' => $user->location,
                'website' => $user->website,
                'avatar' => $user->avatarUrl(),
                'banner' => $user->bannerUrl(),
            ],
            'settings' => array_merge(self::DEFAULTS, $this->stored($user->id)),
        ]);
    }

    public function updateProfile(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'name' => ['required', 'string', 'max:60'],
            'username' => ['required', 'string', 'min:3', 'max:30', 'alpha_dash', Rule::unique('users', 'username')->ignore($request->user()->id)],
            'bio' => ['nullable', 'string', 'max:240'],
            'location' => ['nullable', 'string', 'max:80'],
            'website' => ['nullable', 'string', 'max:120'],
            'avatar' => ['nullable', 'image', 'mimes:jpg,jpeg,png,webp', 'max:5120'],
            'banner' => ['nullable', 'image', 'mimes:jpg,jpeg,png,webp', 'max:10240'],
        ]);

        $user = $request->user();

        if ($request->hasFile('avatar')) {
            $data['avatar_path'] = $request->file('avatar')->store("avatars/{$user->id}", config('filesystems.upload_disk', 'public'));
        }
        if ($request->hasFile('banner')) {
            $data['banner_path'] = $request->file('banner')->store("banners/{$user->id}", config('filesystems.upload_disk', 'public'));
        }

        unset($data['avatar'], $data['banner']);
        $user->update($data);

        return back()->with('success', 'Profile updated.');
    }

    public function update(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'settings' => ['required', 'array'],
        ]);

        $allowed = array_keys(self::DEFAULTS);
        foreach ($data['settings'] as $key => $value) {
            if (! in_array($key, $allowed, true)) {
                continue;
            }
            Setting::updateOrCreate(
                ['user_id' => $request->user()->id, 'key' => $key],
                ['value' => is_bool($value) ? ($value ? '1' : '0') : (string) $value]
            );
        }

        // keep the explicit flag on the users row, because it is what the gallery queries
        if (array_key_exists('show_explicit', $data['settings'])) {
            $request->user()->forceFill(['show_explicit' => (bool) $data['settings']['show_explicit']])->save();
        }

        return back()->with('success', 'Settings saved.');
    }

    private function stored(int $userId): array
    {
        return Setting::where('user_id', $userId)->get()->mapWithKeys(function (Setting $s) {
            $value = match ($s->value) {
                '1', '0' => $s->value === '1',
                default => $s->value,
            };

            return [$s->key => $value];
        })->all();
    }
}
