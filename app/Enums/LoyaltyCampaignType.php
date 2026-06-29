<?php

declare(strict_types=1);

namespace App\Enums;

enum LoyaltyCampaignType: string
{
    case DoublePoints = 'double_points';
    case BonusPoints = 'bonus_points';
    case Multiplier = 'multiplier';

    /**
     * @return list<string>
     */
    public static function values(): array
    {
        return array_column(self::cases(), 'value');
    }
}
