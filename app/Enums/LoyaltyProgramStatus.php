<?php

declare(strict_types=1);

namespace App\Enums;

enum LoyaltyProgramStatus: string
{
    case Draft = 'draft';
    case Active = 'active';
    case Inactive = 'inactive';
    case Expired = 'expired';

    /**
     * @return list<string>
     */
    public static function values(): array
    {
        return array_column(self::cases(), 'value');
    }
}
