<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class PostRating extends Model
{
    protected $fillable = ['post_id', 'user_id', 'stars'];

    protected function casts(): array
    {
        return ['stars' => 'integer'];
    }
}
