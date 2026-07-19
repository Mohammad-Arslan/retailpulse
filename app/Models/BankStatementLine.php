<?php

declare(strict_types=1);

namespace App\Models;

use App\Enums\BankStatementLineStatus;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

#[Fillable([
    'tenant_id',
    'bank_account_id',
    'statement_date',
    'transaction_date',
    'reference',
    'description',
    'debit',
    'credit',
    'running_balance',
    'import_batch_id',
    'status',
])]
class BankStatementLine extends Model
{
    protected function casts(): array
    {
        return [
            'statement_date' => 'date',
            'transaction_date' => 'date',
            'debit' => 'decimal:2',
            'credit' => 'decimal:2',
            'running_balance' => 'decimal:2',
            'status' => BankStatementLineStatus::class,
        ];
    }

    public function bankAccount(): BelongsTo
    {
        return $this->belongsTo(BankAccount::class);
    }

    public function matches(): HasMany
    {
        return $this->hasMany(BankReconciliationMatch::class);
    }

    public function signedAmount(): float
    {
        return (float) $this->debit - (float) $this->credit;
    }
}
