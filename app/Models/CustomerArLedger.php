<?php

declare(strict_types=1);

namespace App\Models;

use App\Enums\ArLedgerEntryType;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[Fillable([
    'tenant_id',
    'customer_id',
    'branch_id',
    'sale_id',
    'entry_type',
    'amount',
    'balance_after',
    'reference',
    'notes',
    'user_id',
])]
class CustomerArLedger extends Model
{
    public $timestamps = false;

    protected $table = 'customer_ar_ledger';

    protected function casts(): array
    {
        return [
            'entry_type' => ArLedgerEntryType::class,
            'amount' => 'decimal:2',
            'balance_after' => 'decimal:2',
            'created_at' => 'datetime',
        ];
    }

    public function customer(): BelongsTo
    {
        return $this->belongsTo(Customer::class);
    }

    public function branch(): BelongsTo
    {
        return $this->belongsTo(Branch::class);
    }

    public function sale(): BelongsTo
    {
        return $this->belongsTo(Sale::class);
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
