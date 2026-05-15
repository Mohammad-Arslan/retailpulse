<?php

declare(strict_types=1);

namespace App\Enums;

enum StockMovementReason: string
{
    case Sale = 'sale';
    case PurchaseReceive = 'purchase_receive';
    case TransferOut = 'transfer_out';
    case TransferIn = 'transfer_in';
    case Adjustment = 'adjustment';
    case Damaged = 'damaged';
    case Return = 'return';

    /**
     * @return list<string>
     */
    public static function values(): array
    {
        return array_column(self::cases(), 'value');
    }

    public function increasesStock(): bool
    {
        return match ($this) {
            self::Sale, self::TransferOut, self::Damaged => false,
            default => true,
        };
    }
}
