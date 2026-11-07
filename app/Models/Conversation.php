<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;

class Conversation extends Model
{
    protected $fillable = ['last_message_at'];

    protected function casts(): array
    {
        return ['last_message_at' => 'datetime'];
    }

    public function participants(): BelongsToMany
    {
        return $this->belongsToMany(User::class, 'conversation_participants')->withPivot(['last_read_at', 'last_read_message_id']);
    }

    public function messages(): HasMany
    {
        return $this->hasMany(Message::class)->orderBy('created_at')->orderBy('id');
    }

    public function lastMessage(): ?Message
    {
        return $this->latestMessage()->first();
    }

    public function latestMessage(): HasOne
    {
        return $this->hasOne(Message::class)->ofMany(['created_at' => 'max', 'id' => 'max']);
    }

    public static function existingBetween(int $a, int $b): ?self
    {
        return static::whereHas('participants', fn ($q) => $q->where('users.id', $a))
            ->whereHas('participants', fn ($q) => $q->where('users.id', $b))
            ->first();
    }

    public static function between(int $a, int $b): self
    {
        return static::existingBetween($a, $b)
            ?? tap(static::create(), function (self $c) use ($a, $b) {
                $c->participants()->attach([$a, $b]);
            });
    }
}
