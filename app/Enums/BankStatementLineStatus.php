<?php

declare(strict_types=1);

namespace App\Enums;

enum BankStatementLineStatus: string
{
    case Unmatched = 'unmatched';
    case Suggested = 'suggested';
    case PartiallyMatched = 'partially_matched';
    case Matched = 'matched';
    case Ignored = 'ignored';
    case Reconciled = 'reconciled';

    /** @return list<string> */
    public static function values(): array
    {
        return array_column(self::cases(), 'value');
    }
}
