<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[Fillable([
    'tenant_id',
    'parent_variant_id',
    'child_variant_id',
    'quantity',
])]
class ProductBundleItem extends Model
{
    protected function casts(): array
    {
        return [
            'quantity' => 'decimal:4',
        ];
    }

    public function parentVariant(): BelongsTo
    {
        return $this->belongsTo(ProductVariant::class, 'parent_variant_id');
    }

    public function childVariant(): BelongsTo
    {
        return $this->belongsTo(ProductVariant::class, 'child_variant_id');
    }
}
