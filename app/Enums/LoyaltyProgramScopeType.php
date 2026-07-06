<?php

declare(strict_types=1);

namespace App\Enums;

enum LoyaltyProgramScopeType: string
{
    case Global = 'global';
    case Branch = 'branch';
    case SelectedBranches = 'selected_branches';

    /**
     * @return list<string>
     */
    public static function values(): array
    {
        return array_column(self::cases(), 'value');
    }
}
