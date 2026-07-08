<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[Fillable([
    'sale_id',
    'product_id',
    'product_variant_id',
    'sku',
    'name',
    'unit_price',
    'quantity',
    'discount_type',
    'discount_value',
    'line_total',
    'tax_rate',
    'tax_amount',
    'line_total_inc_tax',
    'cost_consumed',
    'cogs_journal_entry_id',
])]
class SaleItem extends Model
{
    protected function casts(): array
    {
        return [
            'unit_price' => 'decimal:2',
            'discount_value' => 'decimal:2',
            'line_total' => 'decimal:2',
            'tax_rate' => 'decimal:4',
            'tax_amount' => 'decimal:2',
            'line_total_inc_tax' => 'decimal:2',
            'cost_consumed' => 'decimal:2',
            'quantity' => 'integer',
        ];
    }

    public function sale(): BelongsTo
    {
        return $this->belongsTo(Sale::class);
    }

    public function product(): BelongsTo
    {
        return $this->belongsTo(Product::class);
    }

    public function variant(): BelongsTo
    {
        return $this->belongsTo(ProductVariant::class, 'product_variant_id');
    }

    public function cogsJournalEntry(): BelongsTo
    {
        return $this->belongsTo(JournalEntry::class, 'cogs_journal_entry_id');
    }
}
