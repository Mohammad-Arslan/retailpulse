<?php

declare(strict_types=1);

namespace App\Models;

use App\Enums\SaleStatus;
use App\Enums\TaxMode;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Database\Eloquent\SoftDeletes;

#[Fillable([
    'cart_id',
    'branch_id',
    'warehouse_id',
    'customer_id',
    'cashier_id',
    'status',
    'subtotal',
    'total_discount',
    'tax_total',
    'grand_total',
    'balance_due',
    'currency',
    'tax_mode',
    'notes',
    'is_historical',
    'voided_at',
    'completed_at',
])]
class Sale extends Model
{
    use SoftDeletes;

    protected function casts(): array
    {
        return [
            'status' => SaleStatus::class,
            'tax_mode' => TaxMode::class,
            'subtotal' => 'decimal:2',
            'total_discount' => 'decimal:2',
            'tax_total' => 'decimal:2',
            'grand_total' => 'decimal:2',
            'balance_due' => 'decimal:2',
            'is_historical' => 'boolean',
            'voided_at' => 'datetime',
            'completed_at' => 'datetime',
        ];
    }

    public function cart(): BelongsTo
    {
        return $this->belongsTo(PosCart::class, 'cart_id');
    }

    public function branch(): BelongsTo
    {
        return $this->belongsTo(Branch::class);
    }

    public function warehouse(): BelongsTo
    {
        return $this->belongsTo(Warehouse::class);
    }

    public function customer(): BelongsTo
    {
        return $this->belongsTo(Customer::class);
    }

    public function cashier(): BelongsTo
    {
        return $this->belongsTo(User::class, 'cashier_id');
    }

    public function items(): HasMany
    {
        return $this->hasMany(SaleItem::class);
    }

    public function payments(): HasMany
    {
        return $this->hasMany(SalePayment::class);
    }

    public function invoice(): HasOne
    {
        return $this->hasOne(SaleInvoice::class);
    }
}
