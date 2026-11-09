<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class StudioPlan extends Model
{
    public const STAGES = ['idea', 'in_progress', 'draft', 'ready', 'scheduled', 'published'];

    protected $fillable = [
        'title', 'notes', 'stage', 'deadline_at', 'publish_at', 'timezone', 'post_id', 'comic_chapter_id',
    ];

    protected function casts(): array
    {
        return ['deadline_at' => 'immutable_datetime', 'publish_at' => 'immutable_datetime', 'published_at' => 'immutable_datetime'];
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function post(): BelongsTo
    {
        return $this->belongsTo(Post::class)->withoutGlobalScope('published');
    }

    public function chapter(): BelongsTo
    {
        return $this->belongsTo(ComicChapter::class, 'comic_chapter_id');
    }
}
