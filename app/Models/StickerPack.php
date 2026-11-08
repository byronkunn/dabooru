<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class StickerPack extends Model
{
    protected $fillable = ['user_id', 'title', 'post_ids'];

    protected function casts(): array
    {
        return ['post_ids' => 'array'];
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public static function eligible(User $viewer): Builder
    {
        return Post::visibleTo($viewer)->where('is_original', true)->where('rating', 'sfw')->where('type', 'image')
            ->whereHas('user', fn ($author) => $author->whereNull('banned_at'))
            ->whereHas('media.media', fn ($media) => $media->where('kind', 'image'));
    }
}
