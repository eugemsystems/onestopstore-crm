<?php

namespace App\Enums;

enum RoleEnums
{
    case Admin;
    case SuperAdmin;

    /** @return string[] */
    public static function names(): array
    {
        return array_map(
            fn(self $case) => $case->name,
            self::cases(),
        );
    }
}
