<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

#[Fillable([
    'tenant_id',
    'purchase_order_id',
    'product_variant_id',
    'description',
    'qty_ordered',
    'qty_received',
    'unit_price',
    'price_override_reason',
    'tax_rate',
    'line_total',
    'functional_line_total',
    'currency_code',
    'exchange_rate',
    'sort_order',
])]
class PurchaseOrderItem extends Model
{
    protected function casts(): array
    {
        return [
            'qty_ordered' => 'decimal:4',
            'qty_received' => 'decimal:4',
            'unit_price' => 'decimal:4',
            'tax_rate' => 'decimal:4',
            'line_total' => 'decimal:2',
            'functional_line_total' => 'decimal:2',
            'exchange_rate' => 'decimal:6',
        ];
    }

    public function purchaseOrder(): BelongsTo
    {
        return $this->belongsTo(PurchaseOrder::class);
    }

    public function variant(): BelongsTo
    {
        return $this->belongsTo(ProductVariant::class, 'product_variant_id');
    }

    public function grnItems(): HasMany
    {
        return $this->hasMany(GrnItem::class, 'purchase_order_item_id');
    }
}
