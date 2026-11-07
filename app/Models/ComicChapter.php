<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class ComicChapter extends Model
{
    protected $fillable = ['comic_id', 'number', 'title', 'rating', 'views', 'published_at', 'draft_tags'];

    protected function casts(): array
    {
        return ['published_at' => 'datetime', 'draft_tags' => 'array', 'number' => 'integer', 'views' => 'integer'];
    }

    public function scopePublished(Builder $query): Builder
    {
        return $query->whereNotNull('comic_chapters.published_at')->where('comic_chapters.published_at', '<=', now());
    }

    public function scopeVisibleAfterModeration(Builder $query): Builder
    {
        return $query->whereNotExists(fn ($posts) => $posts->from('posts')
            ->whereColumn('posts.comic_chapter_id', 'comic_chapters.id')
            ->whereNotNull('posts.moderation_hidden_at')
            ->whereNotNull('posts.deleted_at'));
    }

    public function comic(): BelongsTo
    {
        return $this->belongsTo(Comic::class);
    }

    public function pages(): HasMany
    {
        return $this->hasMany(ComicPage::class)->orderBy('position');
    }

    public function pageCount(): int
    {
        return $this->pages()->count();
    }

    public function next(): ?self
    {
        return $this->comic->chapters()->where('number', '>', $this->number)->reorder('number')->first();
    }

    public function previous(): ?self
    {
        return $this->comic->chapters()->where('number', '<', $this->number)->orderByDesc('number')->first();
    }
}
