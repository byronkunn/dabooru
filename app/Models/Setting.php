<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Setting extends Model
{
    public $timestamps = false;

    protected $fillable = ['user_id', 'key', 'value'];

    public static function enabled(int $userId, string $key, bool $default = true): bool
    {
        $value = static::query()->where('user_id', $userId)->where('key', $key)->value('value');

        return $value === null ? $default : $value === '1';
    }
}
