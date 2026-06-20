<?php

declare(strict_types=1);

namespace App\Enums;

enum ArLedgerEntryType: string
{
    case Invoice = 'invoice';
    case Payment = 'payment';
    case CreditNote = 'credit_note';
    case WriteOff = 'write_off';
}
