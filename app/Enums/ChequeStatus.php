<?php

declare(strict_types=1);

namespace App\Enums;

enum ChequeStatus: string
{
    case Pending = 'pending';
    case Deposited = 'deposited';
    case Cleared = 'cleared';
    case Bounced = 'bounced';
    case Cancelled = 'cancelled';

    /** @return list<string> */
    public static function values(): array
    {
        return array_column(self::cases(), 'value');
    }
}
