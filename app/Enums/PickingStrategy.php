<?php

declare(strict_types=1);

namespace App\Enums;

enum PickingStrategy: string
{
    case Fifo = 'fifo';
    case Fefo = 'fefo';

    /**
     * @return list<string>
     */
    public static function values(): array
    {
        return array_column(self::cases(), 'value');
    }

    public function label(): string
    {
        return match ($this) {
            self::Fifo => 'FIFO (first in, first out)',
            self::Fefo => 'FEFO (first expiry, first out)',
        };
    }
}
