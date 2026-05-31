<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Collection;

#[Fillable([
    'product_id',
    'name',
    'sku',
    'barcode',
    'cost_price',
    'sell_price',
    'tax_rate',
    'reorder_point',
    'attributes',
    'is_default',
    'sort_order',
])]
class ProductVariant extends Model
{
    protected function casts(): array
    {
        return [
            'attributes' => 'array',
            'cost_price' => 'decimal:4',
            'sell_price' => 'decimal:4',
            'reorder_point' => 'integer',
            'is_default' => 'boolean',
            'sort_order' => 'integer',
        ];
    }

    public function product(): BelongsTo
    {
        return $this->belongsTo(Product::class);
    }

    public function batches(): HasMany
    {
        return $this->hasMany(ProductBatch::class);
    }

    public function serials(): HasMany
    {
        return $this->hasMany(ProductSerial::class);
    }

    public function bundleItems(): HasMany
    {
        return $this->hasMany(ProductBundleItem::class, 'parent_variant_id');
    }

    public function branchPrices(): HasMany
    {
        return $this->hasMany(BranchProductPrice::class);
    }

    public function displayName(): string
    {
        if ($this->name) {
            return $this->name;
        }

        $attrs = $this->getAttribute('attributes');

        if (is_array($attrs) && $attrs !== []) {
            return collect($attrs)
                ->map(fn ($value, $key) => "{$key}: {$value}")
                ->implode(' / ');
        }

        return $this->product?->name ?? $this->sku;
    }

    /**
     * Resolved bundle lines for POS (combo products).
     *
     * @return Collection<int, array{variant: ProductVariant, quantity: string, product_name: string}>
     */
    public function resolvedBundleLines(): Collection
    {
        $this->loadMissing('bundleItems.childVariant.product');

        return $this->bundleItems->map(fn (ProductBundleItem $item) => [
            'variant' => $item->childVariant,
            'quantity' => (string) $item->quantity,
            'product_name' => $item->childVariant?->product?->name ?? '',
        ]);
    }
}
