<?php

declare(strict_types=1);

namespace App\Support;

use App\Models\FinancialSetting;
use App\Models\FiscalYear;
use App\Models\FiscalYearReopenRequest;

final class FinancialSettingsPresenter
{
    /**
     * @return array<string, mixed>
     */
    public static function forPage(FinancialSetting $settings): array
    {
        return [
            'functional_currency_code' => $settings->functional_currency_code,
            'fiscal_year_start_month' => $settings->fiscal_year_start_month,
            'retained_earnings_account_id' => $settings->retained_earnings_account_id,
            'current_year_earnings_account_id' => $settings->current_year_earnings_account_id,
            'opening_balance_equity_account_id' => $settings->opening_balance_equity_account_id,
            'suspense_account_id' => $settings->suspense_account_id,
            'rounding_account_id' => $settings->rounding_account_id,
            'fx_gain_account_id' => $settings->fx_gain_account_id,
            'fx_loss_account_id' => $settings->fx_loss_account_id,
            'default_inventory_valuation_method' => $settings->default_inventory_valuation_method?->value,
            'allow_negative_inventory' => $settings->allow_negative_inventory,
            'allow_manual_journal_posting' => $settings->allow_manual_journal_posting,
            'manual_journal_approval_limit' => $settings->manual_journal_approval_limit,
            'backdated_posting_policy' => $settings->backdated_posting_policy?->value,
            'backdated_entry_approval_required' => $settings->backdated_entry_approval_required,
            'zero_cost_inventory_policy' => $settings->zero_cost_inventory_policy?->value,
            'fiscal_year_close_approval_required' => $settings->fiscal_year_close_approval_required,
            'period_lock_mode' => $settings->period_lock_mode,
            'journal_numbering_mode' => $settings->journal_numbering_mode,
            'accounting_cutover_date' => $settings->accounting_cutover_date?->toDateString(),
            'fiscal_year_reopen_window_hours' => $settings->fiscal_year_reopen_window_hours,
            'default_sales_tax_type_id' => $settings->default_sales_tax_type_id,
            'default_purchase_tax_type_id' => $settings->default_purchase_tax_type_id,
            'tax_reporting_enabled' => $settings->tax_reporting_enabled,
            'tax_return_frequency' => $settings->tax_return_frequency,
        ];
    }

    /**
     * @return array<string, mixed>
     */
    public static function fiscalYear(FiscalYear $year): array
    {
        return [
            'id' => $year->id,
            'name' => $year->name,
            'legal_entity_id' => $year->legal_entity_id,
            'start_date' => $year->start_date?->toDateString(),
            'end_date' => $year->end_date?->toDateString(),
            'status' => $year->status->value,
            'closed_at' => $year->closed_at?->toIso8601String(),
        ];
    }

    /**
     * @return array<string, mixed>
     */
    public static function reopenRequest(FiscalYearReopenRequest $request): array
    {
        return [
            'id' => $request->id,
            'fiscal_year_id' => $request->fiscal_year_id,
            'fiscal_year_name' => $request->fiscalYear?->name,
            'reason' => $request->reason,
            'requested_by_name' => $request->requestedByUser?->name,
            'first_approved_by' => $request->first_approved_by,
            'first_approved_by_name' => $request->firstApprovedByUser?->name,
            'status' => $request->status,
            'created_at' => $request->created_at?->toIso8601String(),
        ];
    }
}
