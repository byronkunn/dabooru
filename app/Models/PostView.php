<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class PostView extends Model
{
    protected $fillable = ['post_id', 'viewer_key', 'viewed_on'];

    protected function casts(): array
    {
        return ['viewed_on' => 'date'];
    }
}
