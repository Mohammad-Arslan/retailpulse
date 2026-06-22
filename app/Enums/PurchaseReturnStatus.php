<?php

declare(strict_types=1);

namespace App\Enums;

enum PurchaseReturnStatus: string
{
    case Draft = 'draft';
    case Approved = 'approved';
    case GoodsDispatched = 'goods_dispatched';
    case SupplierAcknowledged = 'supplier_acknowledged';
    case DebitNoteIssued = 'debit_note_issued';
    case Closed = 'closed';

    /**
     * @return list<string>
     */
    public static function values(): array
    {
        return array_column(self::cases(), 'value');
    }

    public function canApprove(): bool
    {
        return $this === self::Draft;
    }

    public function canDispatch(): bool
    {
        return $this === self::Approved;
    }

    public function canAcknowledge(): bool
    {
        return $this === self::GoodsDispatched;
    }

    public function canIssueDebitNote(): bool
    {
        return in_array($this, [self::GoodsDispatched, self::SupplierAcknowledged], true);
    }

    public function canClose(): bool
    {
        return $this === self::DebitNoteIssued;
    }
}
