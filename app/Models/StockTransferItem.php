<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[Fillable([
    'stock_transfer_id',
    'product_variant_id',
    'batch_id',
    'qty_requested',
    'qty_received',
])]
class StockTransferItem extends Model
{
    protected function casts(): array
    {
        return [
            'qty_requested' => 'integer',
            'qty_received' => 'integer',
        ];
    }

    public function transfer(): BelongsTo
    {
        return $this->belongsTo(StockTransfer::class, 'stock_transfer_id');
    }

    public function variant(): BelongsTo
    {
        return $this->belongsTo(ProductVariant::class, 'product_variant_id');
    }

    public function batch(): BelongsTo
    {
        return $this->belongsTo(ProductBatch::class, 'batch_id');
    }

    public function qtyRemaining(): int
    {
        return max(0, $this->qty_requested - $this->qty_received);
    }

    public function isFullyReceived(): bool
    {
        return $this->qty_received >= $this->qty_requested;
    }
}
