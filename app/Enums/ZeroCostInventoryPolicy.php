<?php

declare(strict_types=1);

namespace App\Enums;

enum ZeroCostInventoryPolicy: string
{
    case Block = 'block';
    case Warn = 'warn';
    case Allow = 'allow';

    /**
     * @return list<string>
     */
    public static function values(): array
    {
        return array_column(self::cases(), 'value');
    }
}
