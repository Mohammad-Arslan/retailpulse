<?php

declare(strict_types=1);

namespace App\Enums;

enum TaxCalculationMethod: string
{
    case Inclusive = 'inclusive';
    case Exclusive = 'exclusive';

    /** @return list<string> */
    public static function values(): array
    {
        return array_column(self::cases(), 'value');
    }
}
