<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[Fillable([
    'tenant_id',
    'supplier_invoice_id',
    'purchase_order_item_id',
    'grn_item_id',
    'product_variant_id',
    'description',
    'qty_invoiced',
    'unit_price',
    'tax_rate',
    'discount_amount',
    'line_total',
    'functional_line_total',
])]
class SupplierInvoiceItem extends Model
{
    protected function casts(): array
    {
        return [
            'qty_invoiced' => 'decimal:4',
            'unit_price' => 'decimal:4',
            'tax_rate' => 'decimal:4',
            'discount_amount' => 'decimal:2',
            'line_total' => 'decimal:2',
            'functional_line_total' => 'decimal:2',
        ];
    }

    public function invoice(): BelongsTo
    {
        return $this->belongsTo(SupplierInvoice::class, 'supplier_invoice_id');
    }

    public function purchaseOrderItem(): BelongsTo
    {
        return $this->belongsTo(PurchaseOrderItem::class);
    }

    public function grnItem(): BelongsTo
    {
        return $this->belongsTo(GrnItem::class);
    }

    public function variant(): BelongsTo
    {
        return $this->belongsTo(ProductVariant::class, 'product_variant_id');
    }
}
