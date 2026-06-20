<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[Fillable([
    'warehouse_id',
    'product_variant_id',
    'batch_id',
    'bin_location_id',
    'quantity_on_hand',
    'quantity_reserved',
    'quantity_in_quarantine',
])]
class Inventory extends Model
{
    protected function casts(): array
    {
        return [
            'quantity_on_hand' => 'integer',
            'quantity_reserved' => 'integer',
            'quantity_in_quarantine' => 'integer',
        ];
    }

    public function warehouse(): BelongsTo
    {
        return $this->belongsTo(Warehouse::class);
    }

    public function variant(): BelongsTo
    {
        return $this->belongsTo(ProductVariant::class, 'product_variant_id');
    }

    public function batch(): BelongsTo
    {
        return $this->belongsTo(ProductBatch::class, 'batch_id');
    }

    public function binLocation(): BelongsTo
    {
        return $this->belongsTo(BinLocation::class);
    }

    public function availableQuantity(): int
    {
        return max(0, $this->quantity_on_hand - $this->quantity_reserved - $this->quantity_in_quarantine);
    }

    public function sellableQuantity(): int
    {
        return $this->availableQuantity();
    }
}
