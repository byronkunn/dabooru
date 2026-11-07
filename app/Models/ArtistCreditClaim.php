<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class ArtistCreditClaim extends Model
{
    protected $fillable = ['user_id', 'post_id', 'evidence', 'status', 'reason'];
}
