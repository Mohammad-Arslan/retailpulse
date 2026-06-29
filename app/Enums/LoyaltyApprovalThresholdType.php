<?php

declare(strict_types=1);

namespace App\Enums;

enum LoyaltyApprovalThresholdType: string
{
    case Points = 'points';
    case Currency = 'currency';

    /**
     * @return list<string>
     */
    public static function values(): array
    {
        return array_column(self::cases(), 'value');
    }
}
