<?php

declare(strict_types=1);

namespace App\Enums;

enum BackdatedPostingPolicy: string
{
    case Allow = 'allow';
    case Warn = 'warn';
    case Block = 'block';

    /**
     * @return list<string>
     */
    public static function values(): array
    {
        return array_column(self::cases(), 'value');
    }
}
