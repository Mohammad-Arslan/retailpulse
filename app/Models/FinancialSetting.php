<?php

declare(strict_types=1);

namespace App\Models;

use App\Enums\InventoryValuationMethod;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[Fillable([
    'tenant_id',
    'functional_currency_code',
    'fiscal_year_start_month',
    'retained_earnings_account_id',
    'current_year_earnings_account_id',
    'opening_balance_equity_account_id',
    'suspense_account_id',
    'rounding_account_id',
    'fx_gain_account_id',
    'fx_loss_account_id',
    'default_inventory_valuation_method',
    'allow_negative_inventory',
    'allow_manual_journal_posting',
    'manual_journal_approval_limit',
    'backdated_posting_policy',
    'backdated_entry_approval_required',
    'fiscal_year_close_approval_required',
    'period_lock_mode',
    'journal_numbering_mode',
    'default_tax_type_id',
    'accounting_cutover_date',
])]
class FinancialSetting extends Model
{
    protected function casts(): array
    {
        return [
            'default_inventory_valuation_method' => InventoryValuationMethod::class,
            'allow_negative_inventory' => 'boolean',
            'allow_manual_journal_posting' => 'boolean',
            'manual_journal_approval_limit' => 'decimal:2',
            'backdated_entry_approval_required' => 'boolean',
            'fiscal_year_close_approval_required' => 'boolean',
            'accounting_cutover_date' => 'date',
        ];
    }

    public function retainedEarningsAccount(): BelongsTo
    {
        return $this->belongsTo(ChartOfAccount::class, 'retained_earnings_account_id');
    }

    public function openingBalanceEquityAccount(): BelongsTo
    {
        return $this->belongsTo(ChartOfAccount::class, 'opening_balance_equity_account_id');
    }
}
