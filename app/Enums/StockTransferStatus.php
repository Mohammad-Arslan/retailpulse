<?php

declare(strict_types=1);

namespace App\Enums;

enum StockTransferStatus: string
{
    case Draft = 'draft';
    case Shipped = 'shipped';
    case Received = 'received';

    /**
     * @return list<string>
     */
    public static function values(): array
    {
        return array_column(self::cases(), 'value');
    }

    public function canShip(): bool
    {
        return $this === self::Draft;
    }

    public function canReceive(): bool
    {
        return $this === self::Shipped;
    }

    public function isEditable(): bool
    {
        return $this === self::Draft;
    }
}
