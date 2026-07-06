<?php

declare(strict_types=1);

namespace App\Enums;

enum LoyaltyApprovalMode: string
{
    case Pin = 'pin';
    case Workflow = 'workflow';

    /**
     * @return list<string>
     */
    public static function values(): array
    {
        return array_column(self::cases(), 'value');
    }
}
