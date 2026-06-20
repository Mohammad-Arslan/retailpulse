<?php

declare(strict_types=1);

namespace App\Enums;

enum CountScopeType: string
{
    case Full = 'full';
    case Zone = 'zone';
    case Category = 'category';

    /**
     * @return list<string>
     */
    public static function values(): array
    {
        return array_column(self::cases(), 'value');
    }
}
