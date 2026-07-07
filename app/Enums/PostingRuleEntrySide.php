<?php

declare(strict_types=1);

namespace App\Enums;

enum PostingRuleEntrySide: string
{
    case Debit = 'debit';
    case Credit = 'credit';

    /**
     * @return list<string>
     */
    public static function values(): array
    {
        return array_column(self::cases(), 'value');
    }
}
