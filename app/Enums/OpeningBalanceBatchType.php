<?php

declare(strict_types=1);

namespace App\Enums;

enum OpeningBalanceBatchType: string
{
    case FullGl = 'full_gl';
    case ArAging = 'ar_aging';
    case ApAging = 'ap_aging';
    case Inventory = 'inventory';
    case Bank = 'bank';
    case Tax = 'tax';

    /** @return list<string> */
    public static function values(): array
    {
        return array_column(self::cases(), 'value');
    }
}
