<?php

declare(strict_types=1);

namespace App\Models;

use App\Enums\ProductType;
use App\Traits\HasImages;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

#[Fillable([
    'tenant_id',
    'category_id',
    'brand_id',
    'unit_id',
    'type',
    'name',
    'slug',
    'description',
    'tax_group_id',
    'variant_attributes',
    'track_batches',
    'track_serials',
    'is_active',
])]
class Product extends Model
{
    use HasImages;

    protected function casts(): array
    {
        return [
            'type' => ProductType::class,
            'variant_attributes' => 'array',
            'track_batches' => 'boolean',
            'track_serials' => 'boolean',
            'is_active' => 'boolean',
        ];
    }

    public function category(): BelongsTo
    {
        return $this->belongsTo(Category::class);
    }

    public function brand(): BelongsTo
    {
        return $this->belongsTo(Brand::class);
    }

    public function unit(): BelongsTo
    {
        return $this->belongsTo(Unit::class);
    }

    public function variants(): HasMany
    {
        return $this->hasMany(ProductVariant::class)->orderBy('sort_order');
    }

    public function defaultVariant(): ?ProductVariant
    {
        return $this->variants()->where('is_default', true)->first()
            ?? $this->variants()->first();
    }

    public function tracksInventory(): bool
    {
        return match ($this->type) {
            ProductType::Service, ProductType::Digital => false,
            default => true,
        };
    }
}
