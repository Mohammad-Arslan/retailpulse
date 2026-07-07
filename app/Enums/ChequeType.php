<?php

declare(strict_types=1);

namespace App\Enums;

enum ChequeType: string
{
    case Issued = 'issued';
    case Received = 'received';

    /** @return list<string> */
    public static function values(): array
    {
        return array_column(self::cases(), 'value');
    }
}
