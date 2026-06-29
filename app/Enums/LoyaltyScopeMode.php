<?php

declare(strict_types=1);

namespace App\Enums;

enum LoyaltyScopeMode: string
{
    case Global = 'global';
    case Branch = 'branch';

    /**
     * @return list<string>
     */
    public static function values(): array
    {
        return array_column(self::cases(), 'value');
    }
}
