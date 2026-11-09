<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class SavedCollection extends Model
{
    protected $fillable = ['user_id', 'name', 'description', 'cover_post_id', 'is_public'];

    protected function casts(): array
    {
        return ['is_public' => 'boolean'];
    }
}
