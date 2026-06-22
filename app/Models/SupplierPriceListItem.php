<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[Fillable([
    'price_list_id',
    'product_variant_id',
    'unit_price',
    'min_qty',
    'lead_time_days',
    'currency_code',
    'exchange_rate',
    'functional_unit_price',
    'created_by',
    'updated_by',
])]
class SupplierPriceListItem extends Model
{
    protected function casts(): array
    {
        return [
            'unit_price' => 'decimal:4',
            'min_qty' => 'decimal:4',
            'exchange_rate' => 'decimal:6',
            'functional_unit_price' => 'decimal:4',
        ];
    }

    public function priceList(): BelongsTo
    {
        return $this->belongsTo(SupplierPriceList::class, 'price_list_id');
    }

    public function variant(): BelongsTo
    {
        return $this->belongsTo(ProductVariant::class, 'product_variant_id');
    }
}
