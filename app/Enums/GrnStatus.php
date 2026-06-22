<?php

declare(strict_types=1);

namespace App\Enums;

enum GrnStatus: string
{
    case Draft = 'draft';
    case Posted = 'posted';
    case Cancelled = 'cancelled';

    /**
     * @return list<string>
     */
    public static function values(): array
    {
        return array_column(self::cases(), 'value');
    }

    public function canPost(): bool
    {
        return $this === self::Draft;
    }

    public function canCancel(): bool
    {
        return $this === self::Draft;
    }
}
