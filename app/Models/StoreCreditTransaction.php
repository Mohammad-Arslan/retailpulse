<?php

declare(strict_types=1);

namespace App\Models;

use App\Enums\StoreCreditTransactionReason;
use App\Enums\StoreCreditTransactionType;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[Fillable([
    'tenant_id',
    'store_credit_id',
    'amount',
    'type',
    'reason',
    'reference_type',
    'reference_id',
    'user_id',
])]
class StoreCreditTransaction extends Model
{
    public $timestamps = false;

    protected function casts(): array
    {
        return [
            'amount' => 'decimal:2',
            'type' => StoreCreditTransactionType::class,
            'reason' => StoreCreditTransactionReason::class,
            'created_at' => 'datetime',
        ];
    }

    public function storeCredit(): BelongsTo
    {
        return $this->belongsTo(StoreCredit::class);
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
