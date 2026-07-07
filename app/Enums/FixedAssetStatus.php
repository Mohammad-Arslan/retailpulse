<?php

declare(strict_types=1);

namespace App\Enums;

enum FixedAssetStatus: string
{
    case Active = 'active';
    case Disposed = 'disposed';
    case WrittenOff = 'written_off';

    /** @return list<string> */
    public static function values(): array
    {
        return array_column(self::cases(), 'value');
    }
}
