<?php

declare(strict_types=1);

namespace App\Models;

use App\Enums\SupplierInvoiceStatus;
use Carbon\CarbonInterface;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Database\Eloquent\SoftDeletes;

#[Fillable([
    'tenant_id',
    'branch_id',
    'supplier_id',
    'grn_id',
    'purchase_order_id',
    'reference_no',
    'status',
    'invoice_date',
    'due_date',
    'currency_code',
    'exchange_rate',
    'subtotal',
    'tax_total',
    'discount_total',
    'total',
    'functional_total',
    'notes',
    'created_by',
    'updated_by',
])]
class SupplierInvoice extends Model
{
    use SoftDeletes;

    protected function casts(): array
    {
        return [
            'status' => SupplierInvoiceStatus::class,
            'invoice_date' => 'date',
            'due_date' => 'date',
            'exchange_rate' => 'decimal:6',
            'subtotal' => 'decimal:2',
            'tax_total' => 'decimal:2',
            'discount_total' => 'decimal:2',
            'total' => 'decimal:2',
            'functional_total' => 'decimal:2',
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
        return $this->hasMany(SupplierInvoiceItem::class);
    }

    public function matchResult(): HasOne
    {
        return $this->hasOne(PoMatchResult::class);
    }

    public function payments(): HasMany
    {
        return $this->hasMany(SupplierPayment::class);
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function effectiveDueDate(): ?CarbonInterface
    {
        if ($this->due_date !== null) {
            return $this->due_date;
        }

        $terms = $this->supplier?->payment_terms_days;
        if ($terms === null || (int) $terms <= 0 || $this->invoice_date === null) {
            return null;
        }

        return $this->invoice_date->copy()->addDays((int) $terms);
    }
}
