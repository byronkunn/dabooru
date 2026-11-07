<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class ReadingProgress extends Model
{
    protected $fillable = ['user_id', 'comic_id', 'comic_chapter_id', 'page'];
}
