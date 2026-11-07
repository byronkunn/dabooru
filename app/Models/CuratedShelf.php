<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class CuratedShelf extends Model
{
    protected $fillable = ['title', 'description', 'entries', 'published'];
    protected $casts = ['entries' => 'array', 'published' => 'boolean'];
}
