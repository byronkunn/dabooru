<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ModerationAction extends Model
{
    protected $fillable = ['moderator_id', 'action', 'target_type', 'target_id', 'report_id', 'reason', 'meta'];

    protected function casts(): array
    {
        return ['meta' => 'array'];
    }

    public function moderator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'moderator_id');
    }

    public function report(): BelongsTo
    {
        return $this->belongsTo(Report::class);
    }

    public static function record(?User $moderator, string $action, ?string $targetType, ?int $targetId, ?int $reportId, ?string $reason = null, array $meta = []): self
    {
        return static::create([
            'moderator_id' => $moderator?->id,
            'action' => $action,
            'target_type' => $targetType,
            'target_id' => $targetId,
            'report_id' => $reportId,
            'reason' => $reason,
            'meta' => $meta,
        ]);
    }
}
