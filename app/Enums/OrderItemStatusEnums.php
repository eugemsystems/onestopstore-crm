<?php

namespace App\Enums;
enum OrderItemStatusEnums
{
    case Stuck;
    case FromSupplier;
    case WarehousePacking;
    case InTransitToZim;
    case ReadyForCollection;
    case DroppedAtTheDeport;
    case OutOfStock;

    /** @return string[] */
    public static function names(): array
    {
        return array_map(
            fn(self $case) => $case->name,
            self::cases(),
        );
    }
}
