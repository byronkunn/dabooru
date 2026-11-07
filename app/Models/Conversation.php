<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Conversation extends Model
{
    protected $fillable = ['last_message_at'];

    protected function casts(): array
    {
        return ['last_message_at' => 'datetime'];
    }

    public function participants(): BelongsToMany
    {
        return $this->belongsToMany(User::class, 'conversation_participants')->withPivot('last_read_at');
    }

    public function messages(): HasMany
    {
        return $this->hasMany(Message::class)->orderBy('created_at');
    }

    public function lastMessage(): ?Message
    {
        return $this->messages()->latest()->first();
    }

    public static function between(int $a, int $b): self
    {
        return static::whereHas('participants', fn ($q) => $q->where('users.id', $a))
            ->whereHas('participants', fn ($q) => $q->where('users.id', $b))
            ->first()
            ?? tap(static::create(), function (self $c) use ($a, $b) {
                $c->participants()->attach([$a, $b]);
            });
    }
}
