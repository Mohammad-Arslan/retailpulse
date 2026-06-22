<?php

declare(strict_types=1);

namespace App\Enums;

enum ProcurementDocumentType: string
{
    case PurchaseOrder = 'purchase_order';
    case Grn = 'grn';
    case SupplierInvoice = 'supplier_invoice';
    case SupplierPayment = 'supplier_payment';
    case PurchaseReturn = 'purchase_return';
    case DebitNote = 'debit_note';

    /**
     * @return list<string>
     */
    public static function values(): array
    {
        return array_column(self::cases(), 'value');
    }
}
