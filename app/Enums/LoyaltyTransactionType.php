<?php

declare(strict_types=1);

namespace App\Enums;

enum LoyaltyTransactionType: string
{
    case Earn = 'earn';
    case Redeem = 'redeem';
    case Expire = 'expire';
    case Adjustment = 'adjustment';
    case Bonus = 'bonus';
    case Reversal = 'reversal';

    /**
     * @return list<string>
     */
    public static function values(): array
    {
        return array_column(self::cases(), 'value');
    }
}
