<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;

class Bookmark extends Model
{
    protected $fillable = ['user_id', 'post_id'];

    public function collections(): BelongsToMany
    {
        return $this->belongsToMany(SavedCollection::class)->withPivot(['position', 'added_at', 'added_order']);
    }
}
