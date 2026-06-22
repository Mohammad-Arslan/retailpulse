<?php

declare(strict_types=1);

namespace App\Enums;

enum LandedCostAllocationMethod: string
{
    case Quantity = 'quantity';
    case Weight = 'weight';
    case Value = 'value';
    case Manual = 'manual';

    /**
     * @return list<string>
     */
    public static function values(): array
    {
        return array_column(self::cases(), 'value');
    }
}
