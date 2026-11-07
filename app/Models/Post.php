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
        'user_id', 'type', 'rating', 'is_original', 'artist_name', 'source_url',
        'title', 'caption', 'is_draft', 'draft_tags', 'views', 'likes_count', 'comments_count', 'comic_chapter_id',
    ];

    protected function casts(): array
    {
        return [
            'is_original' => 'boolean',
            'is_draft' => 'boolean',
            'draft_tags' => 'array',
            'tag_version' => 'integer',
            'views' => 'integer',
            'likes_count' => 'integer',
            'comments_count' => 'integer',
        ];
    }

    protected static function booted(): void
    {
        static::addGlobalScope('published', fn (Builder $query) => $query->where('posts.is_draft', false));
    }

    public function creditedArtist(): BelongsTo
    {
        return $this->belongsTo(User::class, 'credited_artist_id');
    }

    public function isExternalArtist(): bool
    {
        return ! $this->is_original || ! empty($this->artist_name);
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

    public function tags(): BelongsToMany
    {
        return $this->belongsToMany(Tag::class)->orderBy('name');
    }

    public function bookmarks(): HasMany
    {
        return $this->hasMany(Bookmark::class);
    }

    public function bookmarkedBy(): BelongsToMany
    {
        return $this->belongsToMany(User::class, 'bookmarks');
    }

    /* ---------------- scopes ---------------- */

    public function scopeVisibleTo(Builder $q, ?User $viewer): Builder
    {
        return $q->when($viewer, fn ($q) => $q->where(fn ($authors) => $authors
            ->whereNull('posts.user_id')
            ->orWhereNotIn('posts.user_id', Block::counterpartIds($viewer->id))));
    }

    public function scopeOfType(Builder $q, ?string $type): Builder
    {
        return $type && in_array($type, self::TYPES, true) ? $q->where('type', $type) : $q;
    }

    /**
     * Restrict to works whose media duration falls in [min, max] seconds.
     * Only videos carry a duration, so this also keeps the feed to video.
     */
    public function scopeWithinDuration(Builder $q, ?int $min, ?int $max): Builder
    {
        if ($min === null && $max === null) {
            return $q;
        }

        return $q->whereHas('media.media', function ($media) use ($min, $max) {
            $media->whereNotNull('duration_seconds');
            if ($min !== null) {
                $media->where('duration_seconds', '>=', $min);
            }
            if ($max !== null) {
                $media->where('duration_seconds', '<=', $max);
            }
        });
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

    /**
     * Skip works carrying any of the given tag names.
     *
     * Tags are user-created and open-ended, so this lives here rather than
     * being baked into a fixed list: "no gore" or "no gore, no ntr" both work.
     */
    public function scopeExcludingTags(Builder $q, array $names): Builder
    {
        $names = array_values(array_filter($names, fn ($n) => is_string($n) && $n !== ''));

        return $names === [] ? $q : $q->whereDoesntHave('tags', fn ($tags) => $tags->whereIn('name', $names));
    }

    /** Restrict to works carrying at least one of the given tag names. */
    public function scopeIncludingTags(Builder $q, array $names): Builder
    {
        $names = array_values(array_filter($names, fn ($n) => is_string($n) && $n !== ''));

        return $names === [] ? $q : $q->whereHas('tags', fn ($tags) => $tags->whereIn('name', $names));
    }

    /** A quality floor in the only currency the site records: likes and views. */
    public function scopeAboveThreshold(Builder $q, ?int $minLikes, ?int $minViews): Builder
    {
        return $q
            ->when($minLikes !== null && $minLikes > 0, fn ($q) => $q->where('likes_count', '>=', $minLikes))
            ->when($minViews !== null && $minViews > 0, fn ($q) => $q->where('views', '>=', $minViews));
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
