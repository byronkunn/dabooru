<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\MorphTo;

class Report extends Model
{
    protected $fillable = ['reporter_id', 'reportable_type', 'reportable_id', 'reason', 'details', 'status', 'handled_by', 'resolved_at', 'message_evidence', 'evidence_media_id', 'message_context'];

    public function reporter(): BelongsTo
    {
        return $this->belongsTo(User::class, 'reporter_id');
    }

    protected function casts(): array
    {
        return ['resolved_at' => 'datetime', 'message_context' => 'array'];
    }

    public static function sharedWorkContext(Message $message): ?array
    {
        if (! $message->shared_post_id) {
            return null;
        }

        return [
            'post_id' => $message->shared_post_id,
            'title' => $message->sharedPost?->title ?: 'Shared artwork',
        ];
    }

    public function reportable(): MorphTo
    {
        return $this->morphTo();
    }

    public function handler(): BelongsTo
    {
        return $this->belongsTo(User::class, 'handled_by');
    }

    public function assignee(): BelongsTo
    {
        return $this->belongsTo(User::class, 'assigned_to');
    }

    public const URGENT_REASONS = ['minor', 'non_consensual'];

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
