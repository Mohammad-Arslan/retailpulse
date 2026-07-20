<?php

declare(strict_types=1);

namespace App\Models;

use App\Enums\BackdatedPostingPolicy;
use App\Enums\InventoryValuationMethod;
use App\Enums\NegativeInventoryPolicy;
use App\Enums\ZeroCostInventoryPolicy;
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
    'negative_inventory_policy',
    'allow_manual_journal_posting',
    'manual_journal_approval_limit',
    'backdated_posting_policy',
    'backdated_entry_approval_required',
    'zero_cost_inventory_policy',
    'fiscal_year_close_approval_required',
    'period_lock_mode',
    'journal_numbering_mode',
    'default_tax_type_id',
    'default_sales_tax_type_id',
    'default_purchase_tax_type_id',
    'tax_reporting_enabled',
    'tax_return_frequency',
    'accounting_cutover_date',
    'fiscal_year_reopen_window_hours',
])]
class FinancialSetting extends Model
{
    protected function casts(): array
    {
        return [
            'default_inventory_valuation_method' => InventoryValuationMethod::class,
            'negative_inventory_policy' => NegativeInventoryPolicy::class,
            'allow_manual_journal_posting' => 'boolean',
            'manual_journal_approval_limit' => 'decimal:2',
            'backdated_posting_policy' => BackdatedPostingPolicy::class,
            'backdated_entry_approval_required' => 'boolean',
            'zero_cost_inventory_policy' => ZeroCostInventoryPolicy::class,
            'fiscal_year_close_approval_required' => 'boolean',
            'tax_reporting_enabled' => 'boolean',
            'accounting_cutover_date' => 'date',
            'fiscal_year_reopen_window_hours' => 'integer',
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
