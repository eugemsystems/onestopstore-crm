<?php

namespace App\Traits;

use Illuminate\Support\Str;

trait HasUuidRouteBinding
{
    public static function bootHasUuidRouteBinding()
    {
        static::creating(fn($m) => $m->uuid = (string) Str::uuid());
    }

    public function getRouteKeyName(): string
    {
        return 'uuid';
    }
}
