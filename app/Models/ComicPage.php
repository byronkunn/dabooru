<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ComicPage extends Model
{
    public $timestamps = false;

    protected $fillable = ['comic_chapter_id', 'media_id', 'position'];

    public function chapter(): BelongsTo
    {
        return $this->belongsTo(ComicChapter::class, 'comic_chapter_id');
    }

    public function media(): BelongsTo
    {
        return $this->belongsTo(Media::class);
    }
}
