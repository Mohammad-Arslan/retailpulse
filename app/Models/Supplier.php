<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

#[Fillable([
    'tenant_id',
    'code',
    'name',
    'slug',
    'email',
    'phone',
    'tax_registration_no',
    'payment_terms_days',
    'credit_terms_days',
    'currency_code',
    'balance',
    'notes',
    'on_time_delivery_rate',
    'quality_rejection_rate',
    'last_scored_at',
    'is_active',
    'created_by',
    'updated_by',
])]
class Supplier extends Model
{
    use HasFactory;
    use SoftDeletes;

    protected function casts(): array
    {
        return [
            'is_active' => 'boolean',
            'balance' => 'decimal:2',
            'on_time_delivery_rate' => 'decimal:2',
            'quality_rejection_rate' => 'decimal:2',
            'last_scored_at' => 'datetime',
        ];
    }

    public function contacts(): HasMany
    {
        return $this->hasMany(SupplierContact::class);
    }

    public function addresses(): HasMany
    {
        return $this->hasMany(SupplierAddress::class);
    }

    public function priceLists(): HasMany
    {
        return $this->hasMany(SupplierPriceList::class);
    }

    public function purchaseOrders(): HasMany
    {
        return $this->hasMany(PurchaseOrder::class);
    }

    public function ledgerEntries(): HasMany
    {
        return $this->hasMany(SupplierLedgerEntry::class);
    }

    public function attachments(): HasMany
    {
        return $this->hasMany(SupplierAttachment::class);
    }

    public function performanceScores(): HasMany
    {
        return $this->hasMany(SupplierPerformanceScore::class);
    }

    public function preferredVariants(): HasMany
    {
        return $this->hasMany(ProductVariant::class, 'preferred_supplier_id');
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function updater(): BelongsTo
    {
        return $this->belongsTo(User::class, 'updated_by');
    }
}
