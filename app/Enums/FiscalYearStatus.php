<?php

declare(strict_types=1);

namespace App\Enums;

enum FiscalYearStatus: string
{
    case Open = 'open';
    case Closing = 'closing';
    case Closed = 'closed';
    case Reopening = 'reopening';

    /**
     * @return list<string>
     */
    public static function values(): array
    {
        return array_column(self::cases(), 'value');
    }
}
