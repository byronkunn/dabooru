<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

class User extends Authenticatable
{
    use HasFactory, Notifiable;

    public const RATINGS = ['sfw', 'suggestive', 'explicit'];

    protected $fillable = [
        'name',
        'username',
        'email',
        'password',
        'avatar_path',
        'banner_path',
        'bio',
        'location',
        'website',
        'age_confirmed_at',
        'show_explicit',
    ];

    protected $hidden = [
        'password',
        'remember_token',
    ];

    protected function casts(): array
    {
        return [
            'email_verified_at' => 'datetime',
            'age_confirmed_at' => 'datetime',
            'banned_at' => 'datetime',
            'show_explicit' => 'boolean',
            'password' => 'hashed',
        ];
    }

    /* ---------------- relations ---------------- */

    public function posts(): HasMany
    {
        return $this->hasMany(Post::class);
    }

    public function comics(): HasMany
    {
        return $this->hasMany(Comic::class);
    }

    public function followers(): BelongsToMany
    {
        return $this->belongsToMany(User::class, 'follows', 'following_id', 'follower_id');
    }

    public function following(): BelongsToMany
    {
        return $this->belongsToMany(User::class, 'follows', 'follower_id', 'following_id');
    }

    public function conversations(): BelongsToMany
    {
        return $this->belongsToMany(Conversation::class, 'conversation_participants')
            ->withPivot('last_read_at');
    }

    /* ---------------- helpers ---------------- */

    public function isAdmin(): bool
    {
        return (bool) ($this->attributes['is_admin'] ?? false);
    }

    public function avatarUrl(): string
    {
        return $this->avatar_path ? $this->storedUrl($this->avatar_path) : $this->defaultAvatar();
    }

    public function bannerUrl(): ?string
    {
        return $this->banner_path ? $this->storedUrl($this->banner_path) : null;
    }

    private function storedUrl(string $path): string
    {
        return Str::startsWith($path, ['http://', 'https://', '/'])
            ? $path
            : Storage::disk(config('filesystems.upload_disk', 'public'))->url($path);
    }

    public function defaultAvatar(): string
    {
        // deterministic, local, no third-party request
        $hue = crc32($this->username ?: (string) $this->id) % 360;

        return '/avatar.svg?h='.$hue.'&l='.Str::substr($this->username ?: 'ink', 0, 1);
    }

    public function scopeVerified(Builder $q): Builder
    {
        return $q->whereNotNull('email_verified_at')->whereNull('banned_at');
    }

    public function scopeFollowingPosts(Builder $q, int $userId)
    {
        $ids = Follow::where('follower_id', $userId)->pluck('following_id');

        return $q->whereIn('user_id', $ids->push($userId));
    }
}
