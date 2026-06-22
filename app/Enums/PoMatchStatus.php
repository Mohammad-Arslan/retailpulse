<?php

declare(strict_types=1);

namespace App\Enums;

enum PoMatchStatus: string
{
    case FullyMatched = 'fully_matched';
    case PartiallyMatched = 'partially_matched';
    case Unmatched = 'unmatched';

    /**
     * @return list<string>
     */
    public static function values(): array
    {
        return array_column(self::cases(), 'value');
    }

    public function allowsPayment(): bool
    {
        return $this === self::FullyMatched;
    }

    public function label(): string
    {
        return match ($this) {
            self::FullyMatched => 'Fully Matched',
            self::PartiallyMatched => 'Partially Matched',
            self::Unmatched => 'Unmatched',
        };
    }
}
