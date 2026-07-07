<?php

declare(strict_types=1);

namespace App\Models;

use App\Enums\InventoryValuationMethod;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[Fillable([
    'product_variant_id',
    'warehouse_id',
    'batch_no',
    'received_at',
    'qty_received',
    'qty_remaining',
    'unit_cost',
    'valuation_method',
    'landed_cost_amount',
    'source_reference_type',
    'source_reference_id',
    'status',
])]
class InventoryCostLayer extends Model
{
    protected function casts(): array
    {
        return [
            'received_at' => 'datetime',
            'qty_received' => 'decimal:4',
            'qty_remaining' => 'decimal:4',
            'unit_cost' => 'decimal:4',
            'valuation_method' => InventoryValuationMethod::class,
            'landed_cost_amount' => 'decimal:4',
        ];
    }

    public function variant(): BelongsTo
    {
        return $this->belongsTo(ProductVariant::class, 'product_variant_id');
    }

    public function warehouse(): BelongsTo
    {
        return $this->belongsTo(Warehouse::class);
    }
}
