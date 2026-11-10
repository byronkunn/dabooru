<?php

namespace App\Http\Controllers;

use App\Models\Comment;
use App\Models\Post;
use App\Models\Setting;
use App\Models\User;
use App\Support\ChatAccess;
use App\Support\ProfileImageProcessor;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
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
        'notification_new_work' => true,
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
                'avatarSource' => $user->profileImageSourceUrl('avatar'),
                'bannerSource' => $user->profileImageSourceUrl('banner'),
            ],
            'imageLimits' => [
                'avatar_mb' => config('profile-images.avatar_max_mb'),
                'banner_mb' => config('profile-images.banner_max_mb'),
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
            'avatar' => ['nullable', 'image', 'mimes:jpg,jpeg,png,webp', 'max:'.((int) config('profile-images.avatar_max_mb', 5) * 1024)],
            'banner' => ['nullable', 'image', 'mimes:jpg,jpeg,png,webp', 'max:'.((int) config('profile-images.banner_max_mb', 10) * 1024)],
            'remove_avatar' => ['sometimes', 'boolean'],
            'remove_banner' => ['sometimes', 'boolean'],
        ]);

        $user = $request->user();
        foreach (['avatar', 'banner'] as $type) {
            if ($request->hasFile($type)) {
                $this->saveProfileImageFile($user, $type, $request->file($type));
            } elseif ($request->boolean('remove_'.$type)) {
                $this->removeProfileImage($user, $type);
            }
        }
        unset($data['avatar'], $data['banner'], $data['remove_avatar'], $data['remove_banner']);
        $user->update($data);

        return back()->with('success', 'Profile updated.');
    }

    public function updateImage(Request $request, string $type): RedirectResponse
    {
        $user = $request->user();
        $maxUploadMb = (int) config('profile-images.'.$type.'_max_mb', $type === 'avatar' ? 5 : 10);
        $data = $request->validate([
            $type => ['nullable', 'image', 'mimes:jpg,jpeg,png,webp', 'max:'.($maxUploadMb * 1024)],
            'crop' => ['required_without:remove', 'array:x,y,width,height'],
            'crop.x' => ['required_with:crop', 'numeric', 'min:0', 'max:1'],
            'crop.y' => ['required_with:crop', 'numeric', 'min:0', 'max:1'],
            'crop.width' => ['required_with:crop', 'numeric', 'gt:0', 'max:1'],
            'crop.height' => ['required_with:crop', 'numeric', 'gt:0', 'max:1'],
            'remove' => ['sometimes', 'boolean'],
        ]);
        $displayDisk = config('filesystems.upload_disk', 'public');
        $oldSource = $user->{$type.'_source_path'};
        $oldDisplay = $user->{$type.'_path'};

        if ($request->boolean('remove')) {
            $this->removeProfileImage($user, $type);

            return back()->with('success', ucfirst($type).' removed.');
        }

        $sourceFile = $request->file($type);
        if (! $sourceFile && ! $oldSource && ! $oldDisplay) {
            return back()->withErrors([$type => 'Choose an image first.']);
        }

        $sourcePath = $oldSource;
        $newSource = false;
        if ($sourceFile) {
            $sourcePath = $sourceFile->store("profile-images/{$user->id}/originals", 'local');
            $newSource = true;
        } elseif (! $sourcePath) {
            $sourcePath = "profile-images/{$user->id}/originals/".bin2hex(random_bytes(12)).'.jpg';
            if (! Storage::disk('local')->put($sourcePath, Storage::disk($displayDisk)->get($oldDisplay))) {
                throw new \RuntimeException('Could not preserve the existing profile image.');
            }
            $newSource = true;
        }

        $temporaryPath = null;
        try {
            if ($sourceFile) {
                $imagePath = $sourceFile->getRealPath();
            } else {
                $temporaryPath = tempnam(sys_get_temp_dir(), 'profile-image-');
                file_put_contents($temporaryPath, Storage::disk('local')->get($sourcePath));
                $imagePath = $temporaryPath;
            }

            $cropped = ProfileImageProcessor::crop($imagePath, $type, $data['crop']);
            $displayPath = "{$type}s/{$user->id}/cropped/".bin2hex(random_bytes(12)).'.'.$cropped['extension'];
            if (! Storage::disk($displayDisk)->put($displayPath, $cropped['bytes'], ['visibility' => 'public'])) {
                throw new \RuntimeException('Could not store the edited profile image.');
            }
        } catch (\Throwable $exception) {
            if ($newSource) {
                Storage::disk('local')->delete($sourcePath);
            }
            throw $exception;
        } finally {
            if ($temporaryPath && is_file($temporaryPath)) {
                @unlink($temporaryPath);
            }
        }

        $user->forceFill([$type.'_source_path' => $sourcePath, $type.'_path' => $displayPath])->save();
        if ($oldSource && $oldSource !== $sourcePath) {
            Storage::disk('local')->delete($oldSource);
        }
        if ($oldDisplay && $oldDisplay !== $displayPath && ! Str::startsWith($oldDisplay, ['http://', 'https://', '/'])) {
            Storage::disk($displayDisk)->delete($oldDisplay);
        }

        return back()->with('success', ucfirst($type).' updated.');
    }

    public function imageSource(Request $request, string $type)
    {
        $user = $request->user();
        $sourcePath = $user->{$type.'_source_path'};
        $disk = 'local';
        if (! $sourcePath) {
            $sourcePath = $user->{$type.'_path'};
            $disk = config('filesystems.upload_disk', 'public');
        }
        abort_unless($sourcePath && ! Str::startsWith($sourcePath, ['http://', 'https://', '/'])
            && Storage::disk($disk)->exists($sourcePath), 404);

        return Storage::disk($disk)->response($sourcePath, null, ['Cache-Control' => 'private, no-store']);
    }

    private function saveProfileImageFile(User $user, string $type, \Illuminate\Http\UploadedFile $file): void
    {
        $sourcePath = $file->store("profile-images/{$user->id}/originals", 'local');
        $dimensions = getimagesize($file->getRealPath());
        $ratio = $type === 'avatar' ? 1 : 3;
        $sourceRatio = $dimensions[0] / $dimensions[1];
        $crop = $sourceRatio > $ratio
            ? ['x' => (1 - ($ratio / $sourceRatio)) / 2, 'y' => 0, 'width' => $ratio / $sourceRatio, 'height' => 1]
            : ['x' => 0, 'y' => (1 - ($sourceRatio / $ratio)) / 2, 'width' => 1, 'height' => $sourceRatio / $ratio];
        try {
            $cropped = ProfileImageProcessor::crop($file->getRealPath(), $type, $crop);
            $disk = config('filesystems.upload_disk', 'public');
            $displayPath = "{$type}s/{$user->id}/cropped/".bin2hex(random_bytes(12)).'.'.$cropped['extension'];
            if (! Storage::disk($disk)->put($displayPath, $cropped['bytes'], ['visibility' => 'public'])) {
                throw new \RuntimeException('Could not store the edited profile image.');
            }
        } catch (\Throwable $exception) {
            Storage::disk('local')->delete($sourcePath);
            throw $exception;
        }

        $oldSource = $user->{$type.'_source_path'};
        $oldDisplay = $user->{$type.'_path'};
        $user->forceFill([$type.'_source_path' => $sourcePath, $type.'_path' => $displayPath])->save();
        if ($oldSource) {
            Storage::disk('local')->delete($oldSource);
        }
        if ($oldDisplay && ! Str::startsWith($oldDisplay, ['http://', 'https://', '/'])) {
            Storage::disk(config('filesystems.upload_disk', 'public'))->delete($oldDisplay);
        }
    }

    private function removeProfileImage(User $user, string $type): void
    {
        $oldSource = $user->{$type.'_source_path'};
        $oldDisplay = $user->{$type.'_path'};
        $user->forceFill([$type.'_source_path' => null, $type.'_path' => null])->save();
        if ($oldSource) {
            Storage::disk('local')->delete($oldSource);
        }
        if ($oldDisplay && ! Str::startsWith($oldDisplay, ['http://', 'https://', '/'])) {
            Storage::disk(config('filesystems.upload_disk', 'public'))->delete($oldDisplay);
        }
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
