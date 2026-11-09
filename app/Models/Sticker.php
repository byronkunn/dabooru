<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Sticker extends Model
{
    protected $fillable = ['sticker_pack_id', 'media_id', 'title'];

    public function pack(): BelongsTo
    {
        return $this->belongsTo(StickerPack::class, 'sticker_pack_id');
    }

    public function media(): BelongsTo
    {
        return $this->belongsTo(Media::class);
    }
}
