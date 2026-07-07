<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[Fillable([
    'journal_entry_id',
    'line_sequence',
    'account_id',
    'debit',
    'credit',
    'functional_currency_amount',
    'transaction_currency_amount',
    'currency_code',
    'exchange_rate',
    'cost_centre_id',
    'branch_id',
    'warehouse_id',
    'party_type',
    'party_id',
    'product_variant_id',
    'tax_type_id',
    'reference_type',
    'reference_id',
    'description',
])]
class JournalTransaction extends Model
{
    protected function casts(): array
    {
        return [
            'debit' => 'decimal:2',
            'credit' => 'decimal:2',
            'functional_currency_amount' => 'decimal:2',
            'transaction_currency_amount' => 'decimal:2',
            'exchange_rate' => 'decimal:6',
        ];
    }

    public function journalEntry(): BelongsTo
    {
        return $this->belongsTo(JournalEntry::class);
    }

    public function account(): BelongsTo
    {
        return $this->belongsTo(ChartOfAccount::class, 'account_id');
    }

    public function costCentre(): BelongsTo
    {
        return $this->belongsTo(CostCentre::class);
    }
}
