<?php

declare(strict_types=1);

namespace App\Models;

use App\Enums\WalletTransactionReason;
use App\Enums\WalletTransactionType;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[Fillable([
    'customer_wallet_id',
    'amount',
    'type',
    'reason',
    'reference_type',
    'reference_id',
    'meta',
    'user_id',
])]
class CustomerWalletTransaction extends Model
{
    public $timestamps = false;

    protected function casts(): array
    {
        return [
            'amount' => 'decimal:2',
            'type' => WalletTransactionType::class,
            'reason' => WalletTransactionReason::class,
            'meta' => 'array',
            'created_at' => 'datetime',
        ];
    }

    public function wallet(): BelongsTo
    {
        return $this->belongsTo(CustomerWallet::class, 'customer_wallet_id');
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
