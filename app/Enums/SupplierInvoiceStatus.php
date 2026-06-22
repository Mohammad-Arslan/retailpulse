<?php

declare(strict_types=1);

namespace App\Enums;

enum SupplierInvoiceStatus: string
{
    case Draft = 'draft';
    case Matched = 'matched';
    case Approved = 'approved';
    case Paid = 'paid';
    case Cancelled = 'cancelled';

    /**
     * @return list<string>
     */
    public static function values(): array
    {
        return array_column(self::cases(), 'value');
    }

    public function canMatch(): bool
    {
        return $this === self::Draft;
    }

    public function canApprove(): bool
    {
        return $this === self::Matched;
    }

    public function canPay(): bool
    {
        return in_array($this, [self::Approved, self::Matched], true);
    }

    public function canCancel(): bool
    {
        return in_array($this, [self::Draft, self::Matched], true);
    }

    public function label(): string
    {
        return match ($this) {
            self::Draft => 'Draft',
            self::Matched => 'Matched',
            self::Approved => 'Approved',
            self::Paid => 'Paid',
            self::Cancelled => 'Cancelled',
        };
    }
}
