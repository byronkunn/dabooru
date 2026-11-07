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
}
