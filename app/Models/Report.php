<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\MorphTo;

class Report extends Model
{
    protected $fillable = ['reporter_id', 'reportable_type', 'reportable_id', 'reason', 'details', 'status', 'handled_by', 'resolved_at'];

    public function reporter(): BelongsTo
    {
        return $this->belongsTo(User::class, 'reporter_id');
    }

    protected function casts(): array
    {
        return ['resolved_at' => 'datetime'];
    }

    public function reportable(): MorphTo
    {
        return $this->morphTo();
    }

    public function handler(): BelongsTo
    {
        return $this->belongsTo(User::class, 'handled_by');
    }

    public const REASONS = [
        'minor' => 'Minor / underage or child-like depiction',
        'non_consensual' => 'Non-consensual or stolen',
        'spam' => 'Spam or scam',
        'copyright' => 'Copyright',
        'other' => 'Something else',
    ];

    public const STATUSES = ['open', 'actioned', 'dismissed'];

    public function scopeOpen($q)
    {
        return $q->where('status', 'open');
    }
}
