<?php

declare(strict_types=1);

namespace App\Models;

use App\Enums\SerialStatus;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[Fillable([
    'product_variant_id',
    'serial_number',
    'status',
])]
class ProductSerial extends Model
{
    protected function casts(): array
    {
        return [
            'status' => SerialStatus::class,
        ];
    }

    public function variant(): BelongsTo
    {
        return $this->belongsTo(ProductVariant::class, 'product_variant_id');
    }
}
