<?php

declare(strict_types=1);

namespace App\Enums;

enum WarehouseType: string
{
    case Backroom = 'backroom';
    case SalesFloor = 'sales_floor';
    case Offsite = 'offsite';
    case Central = 'central';

    /**
     * @return list<string>
     */
    public static function values(): array
    {
        return array_column(self::cases(), 'value');
    }
}
