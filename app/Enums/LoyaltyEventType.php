<?php

declare(strict_types=1);

namespace App\Enums;

enum LoyaltyEventType: string
{
    case Purchase = 'purchase';
    case Redeem = 'redeem';
    case Expire = 'expire';
    case Adjustment = 'adjustment';
    case Approval = 'approval';
    case TierChange = 'tier_change';
    case Bonus = 'bonus';

    /**
     * @return list<string>
     */
    public static function values(): array
    {
        return array_column(self::cases(), 'value');
    }
}
