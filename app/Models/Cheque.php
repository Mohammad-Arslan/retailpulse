<?php

declare(strict_types=1);

namespace App\Models;

use App\Enums\ChequeStatus;
use App\Enums\ChequeType;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\MorphTo;

#[Fillable([
    'type',
    'party_type',
    'party_id',
    'amount',
    'currency_id',
    'currency_code',
    'exchange_rate',
    'cheque_no',
    'bank',
    'due_date',
    'status',
    'related_journal_entry_id',
    'dishonour_charge_amount',
    'branch_id',
    'created_by',
])]
class Cheque extends Model
{
    protected function casts(): array
    {
        return [
            'type' => ChequeType::class,
            'amount' => 'decimal:2',
            'exchange_rate' => 'decimal:6',
            'due_date' => 'date',
            'status' => ChequeStatus::class,
            'dishonour_charge_amount' => 'decimal:2',
        ];
    }

    public function party(): MorphTo
    {
        return $this->morphTo();
    }

    public function currency(): BelongsTo
    {
        return $this->belongsTo(Currency::class);
    }

    public function branch(): BelongsTo
    {
        return $this->belongsTo(Branch::class);
    }
}
