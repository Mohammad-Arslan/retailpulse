<?php

declare(strict_types=1);

namespace App\Enums;

enum LoyaltyExpiryType: string
{
    case FixedDays = 'fixed_days';
    case FixedMonths = 'fixed_months';
    case FiscalYear = 'fiscal_year';
    case Never = 'never';

    /**
     * @return list<string>
     */
    public static function values(): array
    {
        return array_column(self::cases(), 'value');
    }
}
