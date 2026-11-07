<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class ComicChapter extends Model
{
    protected $fillable = ['comic_id', 'number', 'title', 'rating', 'views', 'published_at'];

    protected function casts(): array
    {
        return ['published_at' => 'datetime', 'number' => 'integer', 'views' => 'integer'];
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
        return $this->comic->chapters()->where('number', '>', $this->number)->orderBy('number')->first();
    }

    public function previous(): ?self
    {
        return $this->comic->chapters()->where('number', '<', $this->number)->orderByDesc('number')->first();
    }
}
