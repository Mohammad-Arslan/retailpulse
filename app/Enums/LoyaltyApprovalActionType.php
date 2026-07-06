<?php

declare(strict_types=1);

namespace App\Enums;

enum LoyaltyApprovalActionType: string
{
    case ManualAdjustment = 'manual_adjustment';
    case LargeRedemption = 'large_redemption';
    case BonusPoints = 'bonus_points';
    case TierOverride = 'tier_override';

    /**
     * @return list<string>
     */
    public static function values(): array
    {
        return array_column(self::cases(), 'value');
    }
}
