<?php

declare(strict_types=1);

namespace App\Enums;

enum LoyaltyPointType: string
{
    case Earn = 'earn';
    case Redeem = 'redeem';
    case Adjust = 'adjust';
}
