<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[Fillable([
    'tenant_id',
    'cart_id',
    'product_id',
    'product_variant_id',
    'sku',
    'name',
    'unit_price',
    'quantity',
    'discount_type',
    'discount_value',
    'line_total',
    'notes',
])]
class PosCartItem extends Model
{
    protected function casts(): array
    {
        return [
            'unit_price' => 'decimal:2',
            'discount_value' => 'decimal:2',
            'line_total' => 'decimal:2',
            'quantity' => 'integer',
        ];
    }

    public function cart(): BelongsTo
    {
        return $this->belongsTo(PosCart::class, 'cart_id');
    }

    public function product(): BelongsTo
    {
        return $this->belongsTo(Product::class);
    }

    public function variant(): BelongsTo
    {
        return $this->belongsTo(ProductVariant::class, 'product_variant_id');
    }

    public static function computeLineTotal(
        float $unitPrice,
        int $quantity,
        ?string $discountType,
        ?float $discountValue,
    ): float {
        $gross = $unitPrice * $quantity;

        if ($discountType === null || $discountValue === null) {
            return round($gross, 2);
        }

        $discount = match ($discountType) {
            'flat' => $discountValue,
            'percent' => $gross * ($discountValue / 100),
            default => 0.0,
        };

        return round(max(0.0, $gross - $discount), 2);
    }

    public function resolvedLineTotal(): float
    {
        return self::computeLineTotal(
            unitPrice: (float) $this->unit_price,
            quantity: $this->quantity,
            discountType: $this->discount_type,
            discountValue: $this->discount_value !== null ? (float) $this->discount_value : null,
        );
    }

    /** @return array<string, mixed> */
    public function toPosArray(): array
    {
        return [
            'id' => $this->id,
            'cart_id' => $this->cart_id,
            'product_id' => $this->product_id,
            'product_variant_id' => $this->product_variant_id,
            'sku' => $this->sku,
            'name' => $this->name,
            'unit_price' => (float) $this->unit_price,
            'quantity' => $this->quantity,
            'discount_type' => $this->discount_type,
            'discount_value' => $this->discount_value !== null ? (float) $this->discount_value : null,
            'line_total' => $this->resolvedLineTotal(),
            'notes' => $this->notes,
        ];
    }

    protected static function booted(): void
    {
        static::saving(function (PosCartItem $item): void {
            $item->line_total = $item->resolvedLineTotal();
        });
    }
}
