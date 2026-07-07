<?php

declare(strict_types=1);

namespace App\Enums;

enum PettyCashVoucherType: string
{
    case TopUp = 'top_up';
    case Disbursement = 'disbursement';
    case Adjustment = 'adjustment';

    /** @return list<string> */
    public static function values(): array
    {
        return array_column(self::cases(), 'value');
    }
}
