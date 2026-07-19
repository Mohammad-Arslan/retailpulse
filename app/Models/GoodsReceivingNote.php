<?php

declare(strict_types=1);

namespace App\Models;

use App\Enums\GrnStatus;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

#[Fillable([
    'tenant_id',
    'branch_id',
    'purchase_order_id',
    'supplier_id',
    'warehouse_id',
    'reference_no',
    'status',
    'received_at',
    'is_virtual',
    'notes',
    'created_by',
    'updated_by',
])]
class GoodsReceivingNote extends Model
{
    use SoftDeletes;

    protected function casts(): array
    {
        return [
            'status' => GrnStatus::class,
            'received_at' => 'datetime',
            'is_virtual' => 'boolean',
        ];
    }

    public function branch(): BelongsTo
    {
        return $this->belongsTo(Branch::class);
    }

    public function purchaseOrder(): BelongsTo
    {
        return $this->belongsTo(PurchaseOrder::class);
    }

    public function supplier(): BelongsTo
    {
        return $this->belongsTo(Supplier::class);
    }

    public function warehouse(): BelongsTo
    {
        return $this->belongsTo(Warehouse::class);
    }

    public function items(): HasMany
    {
        return $this->hasMany(GrnItem::class, 'grn_id');
    }

    public function landedCostEntries(): HasMany
    {
        return $this->hasMany(LandedCostEntry::class, 'grn_id');
    }

    public function supplierInvoices(): HasMany
    {
        return $this->hasMany(SupplierInvoice::class, 'grn_id');
    }

    public function purchaseReturns(): HasMany
    {
        return $this->hasMany(PurchaseReturn::class, 'grn_id');
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }
}
