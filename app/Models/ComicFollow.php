<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class ComicFollow extends Model
{
    protected $fillable = ['user_id', 'comic_id'];
}
