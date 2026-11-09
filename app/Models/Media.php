<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

class Media extends Model
{
    protected $table = 'media';

    protected $fillable = [
        'user_id', 'disk', 'path', 'thumb_path', 'kind', 'width', 'height',
        'duration_seconds', 'size_bytes', 'status', 'mime_type',
    ];

    protected function casts(): array
    {
        return [
            'width' => 'integer',
            'height' => 'integer',
            'duration_seconds' => 'integer',
            'size_bytes' => 'integer',
        ];
    }

    public function url(): string
    {
        return $this->resolve($this->path);
    }

    public function thumbUrl(): string
    {
        return $this->resolve($this->thumb_path ?: ($this->kind === 'image' ? $this->path : null));
    }

    /**
     * Seeded demo content stores absolute placeholder URLs in `path`;
     * real uploads store a path on the configured disk.
     */
    private function resolve(?string $path): string
    {
        if (! $path) {
            return '';
        }

        // Private DM URLs are generated from the message, where membership is checked.
        if ($this->disk === 'local' && Str::startsWith($path, 'dm/')) {
            return '';
        }

        if ($this->disk === 'local' && Str::startsWith($path, 'drafts/')) {
            return route('studio.media', ['media' => $this->id, 'variant' => $path === $this->path ? 'original' : 'thumb'], false);
        }

        if (Str::startsWith($path, ['http://', 'https://', '/'])) {
            return $path;
        }

        return Storage::disk($this->disk)->url($path);
    }

    public function aspectRatio(): float
    {
        if (! $this->width || ! $this->height) {
            return 1.0;
        }

        return round($this->width / $this->height, 4);
    }

    public function isVideo(): bool
    {
        return $this->kind === 'video';
    }

    public function scopeReady(Builder $q): Builder
    {
        return $q->where('status', 'ready');
    }
}
