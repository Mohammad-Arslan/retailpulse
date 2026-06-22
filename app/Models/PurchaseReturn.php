<?php

declare(strict_types=1);

namespace App\Models;

use App\Enums\PurchaseReturnStatus;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Database\Eloquent\SoftDeletes;

#[Fillable([
    'branch_id',
    'supplier_id',
    'grn_id',
    'purchase_order_id',
    'reference_no',
    'status',
    'reason',
    'notes',
    'approved_by',
    'approved_at',
    'dispatched_at',
    'acknowledged_at',
    'created_by',
    'updated_by',
])]
class PurchaseReturn extends Model
{
    use SoftDeletes;

    protected function casts(): array
    {
        return [
            'status' => PurchaseReturnStatus::class,
            'approved_at' => 'datetime',
            'dispatched_at' => 'datetime',
            'acknowledged_at' => 'datetime',
        ];
    }

    public function branch(): BelongsTo
    {
        return $this->belongsTo(Branch::class);
    }

    public function supplier(): BelongsTo
    {
        return $this->belongsTo(Supplier::class);
    }

    public function grn(): BelongsTo
    {
        return $this->belongsTo(GoodsReceivingNote::class, 'grn_id');
    }

    public function purchaseOrder(): BelongsTo
    {
        return $this->belongsTo(PurchaseOrder::class);
    }

    public function items(): HasMany
    {
        return $this->hasMany(PurchaseReturnItem::class);
    }

    public function debitNote(): HasOne
    {
        return $this->hasOne(DebitNote::class);
    }

    public function approver(): BelongsTo
    {
        return $this->belongsTo(User::class, 'approved_by');
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }
}
