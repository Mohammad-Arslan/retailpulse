<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[Fillable([
    'tenant_id',
    'count_session_id',
    'product_variant_id',
    'bin_location_id',
    'batch_no',
    'system_qty',
    'counted_qty',
    'variance_qty',
    'variance_value',
    'adjustment_reason',
])]
class CountSessionLine extends Model
{
    protected function casts(): array
    {
        return [
            'system_qty' => 'integer',
            'counted_qty' => 'integer',
            'variance_qty' => 'integer',
            'variance_value' => 'decimal:4',
        ];
    }

    public function session(): BelongsTo
    {
        return $this->belongsTo(CountSession::class, 'count_session_id');
    }

    public function variant(): BelongsTo
    {
        return $this->belongsTo(ProductVariant::class, 'product_variant_id');
    }

    public function binLocation(): BelongsTo
    {
        return $this->belongsTo(BinLocation::class);
    }
}
