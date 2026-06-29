<?php

declare(strict_types=1);

namespace App\Enums;

enum LoyaltyRuleType: string
{
    case SpendBased = 'spend_based';
    case ProductBased = 'product_based';
    case CategoryBased = 'category_based';
    case BranchBased = 'branch_based';
    case TimeBased = 'time_based';
    case Birthday = 'birthday';
    case FirstPurchase = 'first_purchase';
    case Campaign = 'campaign';
    case ManualBonus = 'manual_bonus';
    case Redemption = 'redemption';

    /**
     * @return list<string>
     */
    public static function values(): array
    {
        return array_column(self::cases(), 'value');
    }
}
