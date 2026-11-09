<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Message extends Model
{
    protected $fillable = [
        'conversation_id', 'user_id', 'body', 'media_id', 'rating', 'reply_to_id',
        'shared_post_id', 'forwarded_from', 'edited_at', 'pinned_at', 'removed_at', 'expires_at', 'silent', 'interactive', 'spoiler', 'attachment_name', 'sticker_pack_id',
        'media_group', 'media_group_position',
    ];

    protected function casts(): array
    {
        return [
            'edited_at' => 'datetime', 'pinned_at' => 'datetime', 'removed_at' => 'datetime', 'expires_at' => 'datetime',
            'silent' => 'boolean', 'spoiler' => 'boolean', 'interactive' => 'array',
        ];
    }

    public function stickerPack(): BelongsTo
    {
        return $this->belongsTo(StickerPack::class);
    }

    public function sharedPost(): BelongsTo
    {
        return $this->belongsTo(Post::class, 'shared_post_id');
    }

    public function conversation(): BelongsTo
    {
        return $this->belongsTo(Conversation::class);
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /** Optional image or video attached to the message. */
    public function media(): BelongsTo
    {
        return $this->belongsTo(Media::class);
    }
}
