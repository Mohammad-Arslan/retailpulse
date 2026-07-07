<?php

declare(strict_types=1);

namespace App\Enums;

enum IntercompanySettlementStatus: string
{
    case Open = 'open';
    case Settled = 'settled';
    case Cancelled = 'cancelled';

    /** @return list<string> */
    public static function values(): array
    {
        return array_column(self::cases(), 'value');
    }
}
