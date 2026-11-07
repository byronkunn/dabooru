<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Comic extends Model
{
    protected $fillable = [
        'user_id', 'slug', 'title', 'description', 'rating', 'status',
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
        return $this->hasMany(ComicChapter::class)->orderByDesc('number');
    }

    public function latestChapter(): ?ComicChapter
    {
        return $this->chapters()->first();
    }

    public function cover(): ?Media
    {
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

    public function follows(): HasMany
    {
        return $this->hasMany(ComicFollow::class);
    }
}
