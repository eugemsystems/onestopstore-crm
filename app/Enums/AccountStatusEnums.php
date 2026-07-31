<?php

namespace App\Enums;

enum AccountStatusEnums
{
    case active;
    case pending;
    case suspended;
    case disabled;

    /** @return string[] */
    public static function names(): array
    {
        return array_map(
            fn(self $case) => $case->name,
            self::cases(),
        );
    }
}
