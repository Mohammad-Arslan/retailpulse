<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[Fillable([
    'tenant_id',
    'purchase_return_id',
    'grn_item_id',
    'product_variant_id',
    'qty_returned',
    'unit_cost',
    'line_total',
])]
class PurchaseReturnItem extends Model
{
    protected function casts(): array
    {
        return [
            'qty_returned' => 'decimal:4',
            'unit_cost' => 'decimal:4',
            'line_total' => 'decimal:2',
        ];
    }

    public function purchaseReturn(): BelongsTo
    {
        return $this->belongsTo(PurchaseReturn::class);
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
