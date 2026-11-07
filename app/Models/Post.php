<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

class Post extends Model
{
    use SoftDeletes;

    public const TYPES = ['image', 'gif', 'video', 'album', 'comic'];

    protected $fillable = [
        'user_id', 'type', 'rating', 'title', 'caption', 'views',
        'likes_count', 'comments_count', 'comic_chapter_id',
    ];

    protected function casts(): array
    {
        return [
            'views' => 'integer',
            'likes_count' => 'integer',
            'comments_count' => 'integer',
        ];
    }

    /* ---------------- relations ---------------- */

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function media(): HasMany
    {
        return $this->hasMany(PostMedia::class)->orderBy('position');
    }

    public function chapter(): BelongsTo
    {
        return $this->belongsTo(ComicChapter::class, 'comic_chapter_id');
    }

    public function likes(): HasMany
    {
        return $this->hasMany(Like::class);
    }

    public function comments(): HasMany
    {
        return $this->hasMany(Comment::class);
    }

    public function bookmarkedBy(): BelongsToMany
    {
        return $this->belongsToMany(User::class, 'bookmarks');
    }

    /* ---------------- scopes ---------------- */

    public function scopeOfType(Builder $q, ?string $type): Builder
    {
        return $type && in_array($type, self::TYPES, true) ? $q->where('type', $type) : $q;
    }

    public function scopeVisibleRating(Builder $q, bool $showExplicit): Builder
    {
        return $showExplicit ? $q : $q->where('rating', '!=', 'explicit');
    }

    public function scopeWithinWindow(Builder $q, ?string $window): Builder
    {
        return match ($window) {
            'today' => $q->where('created_at', '>=', now()->subDay()),
            'week' => $q->where('created_at', '>=', now()->subWeek()),
            'month' => $q->where('created_at', '>=', now()->subMonth()),
            default => $q,
        };
    }

    public function scopeSorted(Builder $q, string $sort): Builder
    {
        return match ($sort) {
            'views' => $q->orderByDesc('views'),
            'newest' => $q->orderByDesc('created_at'),
            // "top rated" in v1 is likes, with recency as the tie-breaker
            default => $q->orderByDesc('likes_count')->orderByDesc('created_at'),
        };
    }

    public function isUnattributed(): bool
    {
        return $this->user_id === null;
    }

    public function displayAuthor(): string
    {
        return $this->user?->name ?? 'Unsorted';
    }

    public function cover(): ?Media
    {
        return $this->media->first()?->media;
    }
}
