<?php

declare(strict_types=1);

namespace App\Models;

use App\Enums\BankReconciliationMatchType;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[Fillable([
    'bank_statement_line_id',
    'journal_transaction_id',
    'matched_amount',
    'match_type',
    'matched_by',
    'matched_at',
])]
class BankReconciliationMatch extends Model
{
    protected function casts(): array
    {
        return [
            'matched_amount' => 'decimal:2',
            'match_type' => BankReconciliationMatchType::class,
            'matched_at' => 'datetime',
        ];
    }

    public function statementLine(): BelongsTo
    {
        return $this->belongsTo(BankStatementLine::class, 'bank_statement_line_id');
    }

    public function journalTransaction(): BelongsTo
    {
        return $this->belongsTo(JournalTransaction::class);
    }

    public function matchedByUser(): BelongsTo
    {
        return $this->belongsTo(User::class, 'matched_by');
    }
}
