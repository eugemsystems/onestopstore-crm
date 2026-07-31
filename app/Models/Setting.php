<?php

namespace App\Models;

use App\Observers\SettingObserver;
use Illuminate\Database\Eloquent\Attributes\ObservedBy;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

#[ObservedBy([SettingObserver::class])]
class Setting extends Model
{
    use softDeletes;
    protected $fillable = [
        'key',
        'value',
    ];

    public static function value($key)
    {
        return static::where('key', $key)->value('value');
    }


}
