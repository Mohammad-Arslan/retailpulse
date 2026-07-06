<?php

declare(strict_types=1);

namespace App\Models;

use App\Enums\LoyaltyTransactionStatus;
use App\Enums\LoyaltyTransactionType;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\MorphTo;

#[Fillable([
    'tenant_id',
    'customer_id',
    'program_id',
    'wallet_id',
    'branch_id',
    'transaction_type',
    'points',
    'balance_before',
    'balance_after',
    'reference_type',
    'reference_id',
    'reason',
    'status',
    'approved_by',
    'approved_at',
    'created_by',
])]
class CustomerLoyaltyTransaction extends Model
{
    protected function casts(): array
    {
        return [
            'transaction_type' => LoyaltyTransactionType::class,
            'points' => 'integer',
            'balance_before' => 'integer',
            'balance_after' => 'integer',
            'status' => LoyaltyTransactionStatus::class,
            'approved_at' => 'datetime',
        ];
    }

    public function customer(): BelongsTo
    {
        return $this->belongsTo(Customer::class);
    }

    public function program(): BelongsTo
    {
        return $this->belongsTo(LoyaltyProgram::class, 'program_id');
    }

    public function wallet(): BelongsTo
    {
        return $this->belongsTo(CustomerLoyaltyWallet::class, 'wallet_id');
    }

    public function branch(): BelongsTo
    {
        return $this->belongsTo(Branch::class);
    }

    public function approver(): BelongsTo
    {
        return $this->belongsTo(User::class, 'approved_by');
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function reference(): MorphTo
    {
        return $this->morphTo(__FUNCTION__, 'reference_type', 'reference_id');
    }
}
