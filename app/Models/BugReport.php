<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class BugReport extends Model
{
    public const KINDS = ['bug' => 'Something is broken', 'idea' => 'An idea', 'other' => 'Something else'];

    protected $fillable = ['user_id', 'kind', 'title', 'details', 'page_url', 'user_agent', 'viewport', 'status', 'resolved_at'];

    protected function casts(): array
    {
        return ['resolved_at' => 'datetime'];
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
