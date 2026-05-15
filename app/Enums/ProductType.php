<?php

declare(strict_types=1);

namespace App\Enums;

enum ProductType: string
{
    case Standard = 'standard';
    case Variable = 'variable';
    case Service = 'service';
    case Digital = 'digital';
    case Serialized = 'serialized';
    case Combo = 'combo';

    /**
     * @return list<string>
     */
    public static function values(): array
    {
        return array_column(self::cases(), 'value');
    }

    public function hasSingleVariant(): bool
    {
        return $this !== self::Variable;
    }

    public function requiresBundleItems(): bool
    {
        return $this === self::Combo;
    }

    public function tracksSerials(): bool
    {
        return $this === self::Serialized;
    }
}
