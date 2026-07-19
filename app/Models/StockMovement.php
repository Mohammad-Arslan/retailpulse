<?php

declare(strict_types=1);

namespace App\Models;

use App\Enums\StockMovementReason;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\MorphTo;

#[Fillable([
    'tenant_id',
    'warehouse_id',
    'product_variant_id',
    'batch_id',
    'reason',
    'qty_delta',
    'quantity_on_hand_after',
    'reference_type',
    'reference_id',
    'user_id',
    'notes',
])]
class StockMovement extends Model
{
    public $timestamps = false;

    protected static function booted(): void
    {
        static::updating(static fn () => false);
        static::deleting(static fn () => false);
    }

    protected function casts(): array
    {
        return [
            'reason' => StockMovementReason::class,
            'qty_delta' => 'integer',
            'quantity_on_hand_after' => 'integer',
            'created_at' => 'datetime',
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

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function reference(): MorphTo
    {
        return $this->morphTo();
    }
}
