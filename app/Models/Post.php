<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Facades\DB;

class Post extends Model
{
    use SoftDeletes;

    public const TYPES = ['image', 'gif', 'video', 'album', 'comic'];

    protected $fillable = [
        'user_id', 'type', 'rating', 'is_original', 'artist_name', 'source_url',
        'title', 'caption', 'ai_tool', 'ai_tool_type', 'is_draft', 'draft_tags', 'views', 'likes_count', 'comments_count', 'comic_chapter_id',
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
        static::created(function (Post $post): void {
            if (! $post->is_draft) {
                self::notifyFollowersAfterCommit($post);
            }
        });
        static::updated(function (Post $post): void {
            if ($post->wasChanged('is_draft') && ! $post->is_draft) {
                self::notifyFollowersAfterCommit($post);
            }
        });
    }

    /** Notify opted-in followers only after a work is genuinely public. */
    private static function notifyFollowersAfterCommit(Post $post): void
    {
        DB::afterCommit(function () use ($post): void {
            rescue(function () use ($post): void {
                $post->refresh();
                if ($post->is_draft || $post->moderation_hidden_at || ! $post->user_id) {
                    return;
                }

                $followers = DB::table('follows')
                    ->join('users as follower_users', 'follower_users.id', '=', 'follows.follower_id')
                    ->leftJoin('settings as notification_setting', function ($join) {
                        $join->on('notification_setting.user_id', '=', 'follows.follower_id')
                            ->where('notification_setting.key', '=', 'notification_new_work');
                    })
                    ->where('follows.following_id', $post->user_id)
                    ->where(fn ($query) => $query->whereNull('notification_setting.value')->orWhere('notification_setting.value', '1'))
                    ->whereNotIn('follows.follower_id', Block::counterpartIds($post->user_id))
                    ->when($post->rating === 'explicit', fn ($query) => $query->where('follower_users.show_explicit', true))
                    ->orderBy('follows.follower_id')
                    ->pluck('follows.follower_id');

                foreach ($followers->chunk(500) as $batch) {
                    $now = now();
                    DB::table('notifications_app')->insert($batch->map(fn ($userId) => [
                        'user_id' => $userId,
                        'actor_id' => $post->user_id,
                        'type' => 'new_work',
                        'subject_type' => self::class,
                        'subject_id' => $post->id,
                        'data' => json_encode(['post_id' => $post->id]),
                        'created_at' => $now,
                        'updated_at' => $now,
                    ])->all());
                }
            });
        });
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

    public function starRatings(): HasMany
    {
        return $this->hasMany(PostRating::class);
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
        return $this->belongsToMany(User::class, 'bookmarks')->withTimestamps();
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

        return $names === [] ? $q : $q->whereDoesntHave('tags', fn ($tags) => $tags->where('is_approved', true)->whereIn('name', $names));
    }

    /** Restrict to works carrying at least one of the given tag names. */
    public function scopeIncludingTags(Builder $q, array $names): Builder
    {
        $names = array_values(array_filter($names, fn ($n) => is_string($n) && $n !== ''));

        return $names === [] ? $q : $q->whereHas('tags', fn ($tags) => $tags->where('is_approved', true)->whereIn('name', $names));
    }

    /** A quality floor in the only currency the site records: likes and views. */
    public function scopeAboveThreshold(Builder $q, ?int $minLikes, ?int $minViews): Builder
    {
        return $q
            ->when($minLikes !== null && $minLikes > 0, fn ($q) => $q->where('likes_count', '>=', $minLikes))
            ->when($minViews !== null && $minViews > 0, fn ($q) => $q->where('views', '>=', $minViews));
    }

    /**
     * Recompute the denormalised counters from the rows that actually exist.
     *
     * likes_count is a cache, never a source of truth: it is recalculated from
     * the likes table so it can never drift or go negative.
     */
    public function syncLikesCount(): int
    {
        $count = $this->likes()->count();
        $this->forceFill(['likes_count' => $count])->save();

        return $count;
    }

    public function scopeSorted(Builder $q, string $sort): Builder
    {
        return match ($sort) {
            'views' => $q->orderByDesc('views')->orderByDesc('created_at')->orderByDesc('id'),
            'newest' => $q->orderByDesc('created_at')->orderByDesc('id'),
            // "top rated" in v1 is likes, with recency as the tie-breaker
            default => $q->orderByDesc('likes_count')->orderByDesc('created_at')->orderByDesc('id'),
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
