<?php

declare(strict_types=1);

namespace App\Models;

use App\Enums\PoMatchStatus;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[Fillable([
    'purchase_order_id',
    'grn_id',
    'supplier_invoice_id',
    'match_status',
    'qty_variance',
    'price_variance',
    'exception_reason',
    'matched_by',
    'matched_at',
    'resolved_by',
    'resolved_at',
])]
class PoMatchResult extends Model
{
    protected function casts(): array
    {
        return [
            'match_status' => PoMatchStatus::class,
            'qty_variance' => 'decimal:4',
            'price_variance' => 'decimal:4',
            'matched_at' => 'datetime',
            'resolved_at' => 'datetime',
        ];
    }

    public function purchaseOrder(): BelongsTo
    {
        return $this->belongsTo(PurchaseOrder::class);
    }

    public function grn(): BelongsTo
    {
        return $this->belongsTo(GoodsReceivingNote::class, 'grn_id');
    }

    public function supplierInvoice(): BelongsTo
    {
        return $this->belongsTo(SupplierInvoice::class);
    }

    public function matcher(): BelongsTo
    {
        return $this->belongsTo(User::class, 'matched_by');
    }

    public function resolver(): BelongsTo
    {
        return $this->belongsTo(User::class, 'resolved_by');
    }
}
