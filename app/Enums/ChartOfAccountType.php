<?php

declare(strict_types=1);

namespace App\Enums;

enum ChartOfAccountType: string
{
    case Asset = 'asset';
    case Liability = 'liability';
    case Equity = 'equity';
    case Revenue = 'revenue';
    case Expense = 'expense';

    /**
     * @return list<string>
     */
    public static function values(): array
    {
        return array_column(self::cases(), 'value');
    }
}
