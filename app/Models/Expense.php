<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

#[Fillable([
    'expense_number',
    'expense_category_id',
    'branch_id',
    'legal_entity_id',
    'cost_centre_id',
    'vendor_party_type',
    'vendor_party_id',
    'currency_code',
    'exchange_rate',
    'amount',
    'tax_type_id',
    'tax_amount',
    'functional_amount',
    'expense_date',
    'payment_method',
    'description',
    'status',
    'approval_required',
    'approved_by',
    'approved_at',
    'accounting_event_id',
    'journal_entry_id',
    'created_by',
    'updated_by',
])]
class Expense extends Model
{
    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'exchange_rate' => 'decimal:8',
            'amount' => 'decimal:4',
            'tax_amount' => 'decimal:4',
            'functional_amount' => 'decimal:4',
            'expense_date' => 'date',
            'approval_required' => 'boolean',
            'approved_at' => 'datetime',
        ];
    }

    public function category(): BelongsTo
    {
        return $this->belongsTo(ExpenseCategory::class, 'expense_category_id');
    }

    public function branch(): BelongsTo
    {
        return $this->belongsTo(Branch::class);
    }

    public function legalEntity(): BelongsTo
    {
        return $this->belongsTo(OrganizationEntity::class, 'legal_entity_id');
    }

    public function costCentre(): BelongsTo
    {
        return $this->belongsTo(CostCentre::class);
    }

    public function taxType(): BelongsTo
    {
        return $this->belongsTo(TaxType::class);
    }

    public function attachments(): HasMany
    {
        return $this->hasMany(ExpenseAttachment::class);
    }

    public function accountingEvent(): BelongsTo
    {
        return $this->belongsTo(AccountingEvent::class);
    }

    public function approvedByUser(): BelongsTo
    {
        return $this->belongsTo(User::class, 'approved_by');
    }
}
