<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class WikiPage extends Model
{
    protected $guarded = [];

    protected $attributes = ['rating' => 'sfw', 'version' => 0, 'locked' => false];

    protected function casts(): array
    {
        return ['locked' => 'boolean', 'version' => 'integer'];
    }

    public function scopeMatchingSearch($query, string $term)
    {
        if ($term === '') {
            return $query;
        }
        $like = '%'.str_replace(['\\', '%', '_'], ['\\\\', '\\%', '\\_'], $term).'%';

        return $query->where(fn ($query) => $query->where('title', 'like', $like)->orWhere('body', 'like', $like));
    }
}
