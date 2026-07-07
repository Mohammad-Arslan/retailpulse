<?php

declare(strict_types=1);

namespace App\Enums;

enum PettyCashRegisterMode: string
{
    case Imprest = 'imprest';
    case RunningBalance = 'running_balance';

    /** @return list<string> */
    public static function values(): array
    {
        return array_column(self::cases(), 'value');
    }
}
