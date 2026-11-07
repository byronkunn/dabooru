<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class AppNotification extends Model
{
    protected $table = 'notifications_app';

    protected $fillable = ['user_id', 'actor_id', 'type', 'subject_type', 'subject_id', 'data', 'read_at'];

    protected function casts(): array
    {
        return ['read_at' => 'datetime', 'data' => 'array'];
    }

    public function actor(): BelongsTo
    {
        return $this->belongsTo(User::class, 'actor_id');
    }

    public function scopeUnread($q)
    {
        return $q->whereNull('read_at');
    }
}
