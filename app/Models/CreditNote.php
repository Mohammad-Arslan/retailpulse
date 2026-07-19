<?php

declare(strict_types=1);

namespace App\Models;

use App\Enums\CreditNoteStatus;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[Fillable([
    'tenant_id',
    'credit_note_number',
    'customer_id',
    'branch_id',
    'sale_invoice_id',
    'date',
    'currency_id',
    'currency_code',
    'exchange_rate',
    'amount',
    'tax_amount',
    'tax_type_id',
    'reason',
    'status',
    'journal_entry_id',
    'created_by',
])]
class CreditNote extends Model
{
    protected function casts(): array
    {
        return [
            'date' => 'date',
            'exchange_rate' => 'decimal:6',
            'amount' => 'decimal:2',
            'tax_amount' => 'decimal:2',
            'status' => CreditNoteStatus::class,
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

    public function saleInvoice(): BelongsTo
    {
        return $this->belongsTo(SaleInvoice::class);
    }

    public function currency(): BelongsTo
    {
        return $this->belongsTo(Currency::class);
    }

    public function taxType(): BelongsTo
    {
        return $this->belongsTo(TaxType::class);
    }

    public function journalEntry(): BelongsTo
    {
        return $this->belongsTo(JournalEntry::class);
    }

    public function createdByUser(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }
}
