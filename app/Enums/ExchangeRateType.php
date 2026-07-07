<?php

declare(strict_types=1);

namespace App\Enums;

enum ExchangeRateType: string
{
    case Spot = 'spot';
    case Average = 'average';
    case Closing = 'closing';
    case Custom = 'custom';

    /** @return list<string> */
    public static function values(): array
    {
        return array_column(self::cases(), 'value');
    }
}
