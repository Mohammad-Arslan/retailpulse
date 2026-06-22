<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[Fillable([
    'grn_id',
    'purchase_order_item_id',
    'product_variant_id',
    'batch_id',
    'qty_received',
    'expiry_date',
    'notes',
])]
class GrnItem extends Model
{
    protected function casts(): array
    {
        return [
            'qty_received' => 'decimal:4',
            'expiry_date' => 'date',
        ];
    }

    public function grn(): BelongsTo
    {
        return $this->belongsTo(GoodsReceivingNote::class, 'grn_id');
    }

    public function purchaseOrderItem(): BelongsTo
    {
        return $this->belongsTo(PurchaseOrderItem::class);
    }

    public function variant(): BelongsTo
    {
        return $this->belongsTo(ProductVariant::class, 'product_variant_id');
    }

    public function batch(): BelongsTo
    {
        return $this->belongsTo(ProductBatch::class, 'batch_id');
    }
}
