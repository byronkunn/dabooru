<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ReadingProgress extends Model
{
    protected $fillable = ['user_id', 'comic_id', 'comic_chapter_id', 'page'];

    public function comic(): BelongsTo
    {
        return $this->belongsTo(Comic::class);
    }

    public function chapter(): BelongsTo
    {
        return $this->belongsTo(ComicChapter::class, 'comic_chapter_id');
    }
}
