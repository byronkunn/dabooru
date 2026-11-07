<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class DmcaNotice extends Model
{
    public const REASONS = [
        'copyright' => 'Copyright infringement',
        'trademark' => 'Trademark',
        'other' => 'Other',
    ];

    protected $fillable = [
        'reporter_name', 'reporter_email', 'rights_holder', 'url', 'description',
        'good_faith', 'penalty_ack', 'status', 'handled_by', 'resolved_at', 'resolution',
    ];

    protected function casts(): array
    {
        return [
            'good_faith' => 'boolean',
            'penalty_ack' => 'boolean',
            'resolved_at' => 'datetime',
        ];
    }

    public function handler(): BelongsTo
    {
        return $this->belongsTo(User::class, 'handled_by');
    }
}
