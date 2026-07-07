<?php

declare(strict_types=1);

namespace App\Enums;

enum InventoryValuationMethod: string
{
    case Fifo = 'fifo';
    case Wac = 'wac';

    /**
     * @return list<string>
     */
    public static function values(): array
    {
        return array_column(self::cases(), 'value');
    }
}
