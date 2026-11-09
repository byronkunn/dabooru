<?php

namespace App\Http\Controllers;

use App\Models\Comment;
use App\Models\Post;
use App\Models\Setting;
use App\Models\User;
use App\Support\ChatAccess;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;
use Inertia\Inertia;
use Inertia\Response;

class SettingsController extends Controller
{
    private const DEFAULTS = [
        'show_explicit' => true,
        'notification_likes' => true,
        'notification_comments' => true,
        'notification_follows' => true,
    ];

    public function edit(Request $request): Response
    {
        $user = $request->user();

        return Inertia::render('Settings/Index', [
            'user' => [
                'id' => $user->id,
                'name' => $user->name,
                'username' => $user->username,
                'email' => $user->email,
                'email_verified' => (bool) $user->email_verified_at,
                'bio' => $user->bio,
                'location' => $user->location,
                'website' => $user->website,
                'ai_disclosure' => $user->ai_disclosure ?? 'none',
                'avatar' => $user->avatarUrl(),
                'banner' => $user->bannerUrl(),
            ],
            'settings' => array_merge(self::DEFAULTS, $this->stored($user->id), ['show_explicit' => (bool) $user->show_explicit]),
            'mutedTags' => \App\Support\CollectionLibrary::mutedFor($user),
            'mutedTagSuggestions' => \App\Models\Tag::query()
                ->where('is_approved', true)
                ->withCount('posts')
                ->orderByDesc('posts_count')
                ->take(30)
                ->pluck('name')
                ->diff(\App\Support\CollectionLibrary::mutedFor($user))
                ->values()
                ->all(),
            'messagingPrivacy' => [
                'chat_dm_permission' => ChatAccess::permission($user),
                'chat_read_receipts' => Setting::enabled($user->id, 'chat_read_receipts', false),
                'chat_allow_forwarding' => Setting::enabled($user->id, 'chat_allow_forwarding'),
            ],
        ]);
    }

    /** Add a tag to the account-wide mute list. */
    public function muteTag(Request $request): RedirectResponse
    {
        $data = $request->validate(['tag' => ['required', 'string', 'max:40']]);
        \App\Support\CollectionLibrary::addMute($request->user(), $data['tag']);

        return back()->with('success', 'Tag muted. Works with this tag are hidden across the site.');
    }

    /** Remove a tag from the account-wide mute list. */
    public function unmuteTag(Request $request): RedirectResponse
    {
        $data = $request->validate(['tag' => ['required', 'string', 'max:40']]);
        \App\Support\CollectionLibrary::removeMute($request->user(), $data['tag']);

        return back()->with('success', 'Tag unmuted.');
    }

    public function updateProfile(Request $request): RedirectResponse
    {
        if (is_string($request->input('username'))) {
            $request->merge(['username' => strtolower(trim($request->input('username')))]);
        }
        $data = $request->validate([
            'name' => ['required', 'string', 'max:60'],
            'username' => ['required', 'string', 'min:3', 'max:30', 'alpha_dash', Rule::unique('users', 'username')->ignore($request->user()->id)],
            'bio' => ['nullable', 'string', 'max:240'],
            'location' => ['nullable', 'string', 'max:80'],
            'website' => ['nullable', 'url:http,https', 'max:120'],
            'ai_disclosure' => ['sometimes', 'required', Rule::in(['none', 'ai_artist', 'ai_works', 'both'])],
            'avatar' => ['nullable', 'image', 'mimes:jpg,jpeg,png,webp', 'max:5120'],
            'banner' => ['nullable', 'image', 'mimes:jpg,jpeg,png,webp', 'max:10240'],
            'remove_avatar' => ['sometimes', 'boolean'],
            'remove_banner' => ['sometimes', 'boolean'],
        ]);

        $user = $request->user();

        if ($request->hasFile('avatar')) {
            $data['avatar_path'] = $request->file('avatar')->store("avatars/{$user->id}", config('filesystems.upload_disk', 'public'));
        }
        if ($request->hasFile('banner')) {
            $data['banner_path'] = $request->file('banner')->store("banners/{$user->id}", config('filesystems.upload_disk', 'public'));
        }
        foreach (['avatar', 'banner'] as $type) {
            if ($request->boolean('remove_'.$type) && ! $request->hasFile($type)) {
                $data[$type.'_path'] = null;
            }
        }

        unset($data['avatar'], $data['banner'], $data['remove_avatar'], $data['remove_banner']);
        $user->update($data);

        return back()->with('success', 'Profile updated.');
    }

    public function updateImage(Request $request, string $type): RedirectResponse
    {
        $request->validate([
            $type => ['required', 'image', 'mimes:jpg,jpeg,png,webp', 'max:'.($type === 'avatar' ? 5120 : 10240)],
        ]);

        $user = $request->user();
        $path = $request->file($type)->store("{$type}s/{$user->id}", config('filesystems.upload_disk', 'public'));
        $user->update([$type.'_path' => $path]);

        return back()->with('success', ucfirst($type).' updated.');
    }

    public function update(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'settings' => ['required', 'array:'.implode(',', array_keys(self::DEFAULTS)), 'min:1'],
            'settings.*' => ['required', 'boolean'],
        ]);

        DB::transaction(function () use ($request, $data) {
            $user = User::whereKey($request->user()->id)->lockForUpdate()->firstOrFail();
            foreach ($data['settings'] as $key => $value) {
                Setting::updateOrCreate(
                    ['user_id' => $user->id, 'key' => $key],
                    ['value' => (bool) $value ? '1' : '0']
                );
            }
            if (array_key_exists('show_explicit', $data['settings'])) {
                $user->forceFill(['show_explicit' => (bool) $data['settings']['show_explicit']])->save();
            }
        });

        return back()->with('success', 'Settings saved.');
    }

    public function updateEmail(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'email' => ['required', 'email', 'max:255', Rule::unique('users')->ignore($request->user()->id)],
            'current_password' => ['required', 'current_password'],
        ]);
        $user = $request->user();
        if ($user->email !== $data['email']) {
            $user->forceFill(['email' => $data['email'], 'email_verified_at' => null])->save();
            $user->sendEmailVerificationNotification();
        }

        return back()->with('success', $user->email_verified_at ? 'Email saved.' : 'Email saved. Verify your new address before contributing.');
    }

    public function destroyAccount(Request $request): RedirectResponse
    {
        $request->validate([
            'password' => ['required', 'current_password'],
        ]);

        $user = $request->user();

        DB::transaction(function () use ($user) {
            User::whereKey($user->id)->lockForUpdate()->firstOrFail();
            $affected = Comment::withTrashed()->where('user_id', $user->id)->pluck('post_id')
                ->merge(DB::table('likes')->where('user_id', $user->id)->pluck('post_id'))->unique();
            Post::withoutGlobalScope('published')->withTrashed()->where('user_id', $user->id)->get()->each->forceDelete();
            Auth::logout();
            $user->delete();
            foreach (Post::withoutGlobalScope('published')->withTrashed()->whereIn('id', $affected)->get() as $post) {
                $post->forceFill(['comments_count' => $post->comments()->count(), 'likes_count' => $post->likes()->count()])->save();
            }
        });

        $request->session()->invalidate();
        $request->session()->regenerateToken();

        return redirect()->route('gallery')->with('success', 'Your account has been deleted.');
    }

    private function stored(int $userId): array
    {
        return Setting::where('user_id', $userId)->whereIn('key', array_keys(self::DEFAULTS))->get()->mapWithKeys(function (Setting $s) {
            $value = match ($s->value) {
                '1', '0' => $s->value === '1',
                default => $s->value,
            };

            return [$s->key => $value];
        })->all();
    }
}
