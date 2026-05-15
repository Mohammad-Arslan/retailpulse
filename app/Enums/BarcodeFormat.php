<?php

declare(strict_types=1);

namespace App\Enums;

enum BarcodeFormat: string
{
    case Internal = 'internal';
    case Ean13 = 'ean13';
    case Upca = 'upca';
    case Code128 = 'code128';

    /**
     * @return list<string>
     */
    public static function values(): array
    {
        return array_column(self::cases(), 'value');
    }
}
