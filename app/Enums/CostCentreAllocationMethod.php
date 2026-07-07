<?php

declare(strict_types=1);

namespace App\Enums;

enum CostCentreAllocationMethod: string
{
    case Percentage = 'percentage';
    case Headcount = 'headcount';
    case RevenueShare = 'revenue_share';
    case FloorArea = 'floor_area';
    case EqualSplit = 'equal_split';
    case Manual = 'manual';

    /**
     * @return list<string>
     */
    public static function values(): array
    {
        return array_column(self::cases(), 'value');
    }
}
