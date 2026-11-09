<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Comic extends Model
{
    protected $fillable = [
        'user_id', 'slug', 'title', 'cover_media_id', 'description', 'rating', 'status',
        'views', 'likes_count', 'last_chapter_at',
    ];

    protected function casts(): array
    {
        return ['last_chapter_at' => 'datetime', 'views' => 'integer', 'likes_count' => 'integer'];
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function chapters(): HasMany
    {
        return $this->hasMany(ComicChapter::class)->published()->visibleAfterModeration()->orderByDesc('number');
    }

    public function latestChapter(): ?ComicChapter
    {
        return $this->chapters()->first();
    }

    public function coverMedia(): BelongsTo
    {
        return $this->belongsTo(Media::class, 'cover_media_id');
    }

    public function cover(): ?Media
    {
        if ($this->cover_media_id) {
            return $this->coverMedia;
        }

        $latest = $this->relationLoaded('chapters')
            ? $this->chapters->first()
            : $this->chapters()->first();

        if (! $latest) {
            return null;
        }

        $page = $latest->relationLoaded('pages')
            ? $latest->pages->first()
            : $latest->pages()->first();

        return $page?->media;
    }

    public function scopeVisibleRating($q, bool $showExplicit)
    {
        return $showExplicit ? $q : $q->where('rating', '!=', 'explicit');
    }

    public function scopeVisibleTo($query, ?User $viewer)
    {
        return $query->when($viewer, fn ($comics) => $comics->whereNotIn('comics.user_id', Block::counterpartIds($viewer->id)));
    }

    public function scopeMatchingSearch($query, string $term)
    {
        if ($term === '') {
            return $query;
        }
        $like = '%'.str_replace(['\\', '%', '_'], ['\\\\', '\\%', '\\_'], $term).'%';

        return $query->where(fn ($query) => $query->where('title', 'like', $like)
            ->orWhere('description', 'like', $like)
            ->orWhereHas('user', fn ($user) => $user->where('name', 'like', $like)->orWhere('username', 'like', $like))
            ->orWhereHas('chapters', fn ($chapter) => $chapter->where('title', 'like', $like)));
    }

    public function follows(): HasMany
    {
        return $this->hasMany(ComicFollow::class);
    }
}
