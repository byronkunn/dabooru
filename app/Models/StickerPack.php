<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class StickerPack extends Model
{
    protected $fillable = ['user_id', 'title', 'post_ids', 'is_public'];

    protected function casts(): array
    {
        return ['post_ids' => 'array', 'is_public' => 'boolean'];
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function stickers(): HasMany
    {
        return $this->hasMany(Sticker::class);
    }

    public function scopeAvailableTo(Builder $query, User $viewer): Builder
    {
        return $query->where(fn ($visibility) => $visibility->where('is_public', true)->orWhere('user_id', $viewer->id))
            ->whereNotIn('user_id', Block::counterpartIds($viewer->id))
            ->whereHas('user', fn ($author) => $author->whereNull('banned_at'));
    }

    public static function eligible(User $viewer): Builder
    {
        return Post::visibleTo($viewer)->where('is_original', true)->where('rating', 'sfw')->where('type', 'image')
            ->whereHas('user', fn ($author) => $author->whereNull('banned_at'))
            ->whereHas('media.media', fn ($media) => $media->where('kind', 'image'));
    }
}
