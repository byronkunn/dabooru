<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Follow extends Model
{
    protected $fillable = ['follower_id', 'following_id'];

    protected static function booted(): void
    {
        static::created(fn (Follow $follow) => \Illuminate\Support\Facades\DB::table('follower_changes')->insert([
            'user_id' => $follow->following_id, 'change' => 1, 'created_at' => $follow->created_at,
        ]));
        static::deleted(fn (Follow $follow) => \Illuminate\Support\Facades\DB::table('follower_changes')->insert([
            'user_id' => $follow->following_id, 'change' => -1, 'created_at' => now(),
        ]));
    }
}
