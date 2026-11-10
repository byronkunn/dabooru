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
        'avatar_source_path',
        'banner_path',
        'banner_source_path',
        'bio',
        'location',
        'website',
        'ai_disclosure',
        'age_confirmed_at',
        'show_explicit',
    ];

    protected $hidden = [
        'password',
        'remember_token',
        'staff_roles',
    ];

    protected function casts(): array
    {
        return [
            'is_unsorted' => 'boolean',
            'is_admin' => 'boolean',
            'staff_roles' => 'array',
            'email_verified_at' => 'datetime',
            'age_confirmed_at' => 'datetime',
            'banned_at' => 'datetime',
            'show_explicit' => 'boolean',
            'contributions_restricted' => 'boolean',
            'password' => 'hashed',
        ];
    }

    protected static function booted(): void
    {
        static::deleting(function (User $user): void {
            Follow::where('follower_id', $user->id)->get()->each->delete();
        });
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

    public function followedTags(): BelongsToMany
    {
        return $this->belongsToMany(Tag::class);
    }

    public function conversations(): BelongsToMany
    {
        return $this->belongsToMany(Conversation::class, 'conversation_participants')
            ->withPivot('last_read_at');
    }

    /**
     * Route model binding by handle: /u/{user} resolves by username, not id,
     * so follow/block URLs read as /u/rin_senpai/follow.
     */
    public function getRouteKeyName(): string
    {
        return 'username';
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

    public function profileImageSourceUrl(string $type): ?string
    {
        if (! in_array($type, ['avatar', 'banner'], true)) {
            return null;
        }

        return $this->{$type.'_path'} ? route('settings.profile.image.source', $type, false) : null;
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

    public function isBanned(): bool
    {
        return $this->banned_at !== null;
    }

    public function blocks(): HasMany
    {
        return $this->hasMany(Block::class, 'blocker_id');
    }

    /** Ids of everyone this user has blocked. */
    public function blockedUserIds(): array
    {
        return Block::where('blocker_id', $this->id)->pluck('blocked_id')->all();
    }

    public function hasBlocked(User $other): bool
    {
        return Block::existsBetween($this->id, $other->id);
    }

    public function isUnsorted(): bool
    {
        return (bool) $this->is_unsorted;
    }

    public function scopeVerified(Builder $q): Builder
    {
        return $q->whereNotNull('email_verified_at')->whereNull('banned_at')->where('is_unsorted', false);
    }

    public function scopeFollowingPosts(Builder $q, int $userId)
    {
        $ids = Follow::where('follower_id', $userId)->pluck('following_id');

        return $q->whereIn('user_id', $ids->push($userId));
    }
}
