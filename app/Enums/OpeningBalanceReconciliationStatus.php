<?php

declare(strict_types=1);

namespace App\Enums;

enum OpeningBalanceReconciliationStatus: string
{
    case Pending = 'pending';
    case Reconciled = 'reconciled';
    case Unreconciled = 'unreconciled';

    /** @return list<string> */
    public static function values(): array
    {
        return array_column(self::cases(), 'value');
    }
}
