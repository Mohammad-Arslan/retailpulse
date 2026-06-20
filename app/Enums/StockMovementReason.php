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
    case ReturnCustomer = 'return_customer';
    case ReturnSupplier = 'return_supplier';
    case ProductionConsume = 'production_consume';
    case ProductionOutput = 'production_output';
    case CycleCountAdjustment = 'cycle_count_adjustment';
    case BinTransferOut = 'bin_transfer_out';
    case BinTransferIn = 'bin_transfer_in';
    case QuarantineRelease = 'quarantine_release';
    case QuarantineScrap = 'quarantine_scrap';

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
            self::Sale,
            self::TransferOut,
            self::Damaged,
            self::BinTransferOut,
            self::ProductionConsume,
            self::QuarantineScrap => false,
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
