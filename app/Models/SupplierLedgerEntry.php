<?php

declare(strict_types=1);

namespace App\Models;

use App\Enums\SupplierLedgerEntryType;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\MorphTo;

#[Fillable([
    'tenant_id',
    'branch_id',
    'supplier_id',
    'entry_type',
    'amount',
    'balance_after',
    'currency_code',
    'exchange_rate',
    'functional_amount',
    'reference_type',
    'reference_id',
    'reference_no',
    'notes',
    'user_id',
])]
class SupplierLedgerEntry extends Model
{
    public $timestamps = false;

    protected function casts(): array
    {
        return [
            'entry_type' => SupplierLedgerEntryType::class,
            'amount' => 'decimal:2',
            'balance_after' => 'decimal:2',
            'exchange_rate' => 'decimal:6',
            'functional_amount' => 'decimal:2',
            'created_at' => 'datetime',
        ];
    }

    public function supplier(): BelongsTo
    {
        return $this->belongsTo(Supplier::class);
    }

    public function branch(): BelongsTo
    {
        return $this->belongsTo(Branch::class);
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
