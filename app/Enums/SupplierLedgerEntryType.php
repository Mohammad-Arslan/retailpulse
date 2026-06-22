<?php

declare(strict_types=1);

namespace App\Enums;

enum SupplierLedgerEntryType: string
{
    case Invoice = 'invoice';
    case Payment = 'payment';
    case DebitNote = 'debit_note';
    case Adjustment = 'adjustment';
    case Advance = 'advance';

    /**
     * @return list<string>
     */
    public static function values(): array
    {
        return array_column(self::cases(), 'value');
    }
}
