<?php

declare(strict_types=1);

namespace App\Enums;

enum StockMovementReason: string
{
    case OpeningBalance = 'opening_balance';
    case Adjustment = 'adjustment';
    case Damaged = 'damaged';
    case Sale = 'sale';
    case SaleReturn = 'sale_return';
    case TransferOut = 'transfer_out';
    case TransferIn = 'transfer_in';
    case Reserved = 'reserved';
    case ReservationReleased = 'reservation_released';
    case PurchaseReceive = 'purchase_receive';

    /**
     * @return list<string>
     */
    public static function values(): array
    {
        return array_column(self::cases(), 'value');
    }

    /**
     * @return list<string>
     */
    public static function bulkAdjustmentImportValues(): array
    {
        return [
            self::Adjustment->value,
            self::Damaged->value,
        ];
    }

    public function increasesStock(): bool
    {
        return match ($this) {
            self::Sale, self::TransferOut, self::Damaged => false,
            default => true,
        };
    }

    public function affectsOnHand(): bool
    {
        return match ($this) {
            self::Reserved, self::ReservationReleased => false,
            default => true,
        };
    }
}
