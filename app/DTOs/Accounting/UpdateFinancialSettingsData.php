<?php

declare(strict_types=1);

namespace App\DTOs\Accounting;

use App\Enums\InventoryValuationMethod;
use App\Http\Requests\Admin\Accounting\UpdateFinancialSettingsRequest;

final readonly class UpdateFinancialSettingsData
{
    public function __construct(
        public ?string $functionalCurrencyCode,
        public ?int $fiscalYearStartMonth,
        public ?int $retainedEarningsAccountId,
        public ?int $currentYearEarningsAccountId,
        public ?int $openingBalanceEquityAccountId,
        public ?int $suspenseAccountId,
        public ?int $roundingAccountId,
        public ?int $fxGainAccountId,
        public ?int $fxLossAccountId,
        public ?InventoryValuationMethod $defaultInventoryValuationMethod,
        public ?string $negativeInventoryPolicy,
        public ?bool $allowManualJournalPosting,
        public ?float $manualJournalApprovalLimit,
        public ?string $backdatedPostingPolicy,
        public ?bool $backdatedEntryApprovalRequired,
        public ?string $zeroCostInventoryPolicy,
        public ?bool $fiscalYearCloseApprovalRequired,
        public ?string $periodLockMode,
        public ?string $journalNumberingMode,
        public ?string $accountingCutoverDate,
        public ?int $fiscalYearReopenWindowHours,
        public ?int $defaultSalesTaxTypeId,
        public ?int $defaultPurchaseTaxTypeId,
        public ?bool $taxReportingEnabled,
        public ?string $taxReturnFrequency,
    ) {}

    public static function fromRequest(UpdateFinancialSettingsRequest $request): self
    {
        $method = $request->validated('default_inventory_valuation_method');

        return new self(
            functionalCurrencyCode: $request->validated('functional_currency_code'),
            fiscalYearStartMonth: $request->validated('fiscal_year_start_month') !== null
                ? (int) $request->validated('fiscal_year_start_month') : null,
            retainedEarningsAccountId: $request->validated('retained_earnings_account_id') !== null
                ? (int) $request->validated('retained_earnings_account_id') : null,
            currentYearEarningsAccountId: $request->validated('current_year_earnings_account_id') !== null
                ? (int) $request->validated('current_year_earnings_account_id') : null,
            openingBalanceEquityAccountId: $request->validated('opening_balance_equity_account_id') !== null
                ? (int) $request->validated('opening_balance_equity_account_id') : null,
            suspenseAccountId: $request->validated('suspense_account_id') !== null
                ? (int) $request->validated('suspense_account_id') : null,
            roundingAccountId: $request->validated('rounding_account_id') !== null
                ? (int) $request->validated('rounding_account_id') : null,
            fxGainAccountId: $request->validated('fx_gain_account_id') !== null
                ? (int) $request->validated('fx_gain_account_id') : null,
            fxLossAccountId: $request->validated('fx_loss_account_id') !== null
                ? (int) $request->validated('fx_loss_account_id') : null,
            defaultInventoryValuationMethod: $method !== null ? InventoryValuationMethod::from($method) : null,
            negativeInventoryPolicy: $request->validated('negative_inventory_policy'),
            allowManualJournalPosting: $request->has('allow_manual_journal_posting') ? $request->boolean('allow_manual_journal_posting') : null,
            manualJournalApprovalLimit: $request->validated('manual_journal_approval_limit') !== null
                ? (float) $request->validated('manual_journal_approval_limit') : null,
            backdatedPostingPolicy: $request->validated('backdated_posting_policy'),
            backdatedEntryApprovalRequired: $request->has('backdated_entry_approval_required')
                ? $request->boolean('backdated_entry_approval_required') : null,
            zeroCostInventoryPolicy: $request->validated('zero_cost_inventory_policy'),
            fiscalYearCloseApprovalRequired: $request->has('fiscal_year_close_approval_required')
                ? $request->boolean('fiscal_year_close_approval_required') : null,
            periodLockMode: $request->validated('period_lock_mode'),
            journalNumberingMode: $request->validated('journal_numbering_mode'),
            accountingCutoverDate: $request->validated('accounting_cutover_date'),
            fiscalYearReopenWindowHours: $request->validated('fiscal_year_reopen_window_hours') !== null
                ? (int) $request->validated('fiscal_year_reopen_window_hours') : null,
            defaultSalesTaxTypeId: $request->validated('default_sales_tax_type_id') !== null
                ? (int) $request->validated('default_sales_tax_type_id') : null,
            defaultPurchaseTaxTypeId: $request->validated('default_purchase_tax_type_id') !== null
                ? (int) $request->validated('default_purchase_tax_type_id') : null,
            taxReportingEnabled: $request->has('tax_reporting_enabled')
                ? $request->boolean('tax_reporting_enabled') : null,
            taxReturnFrequency: $request->validated('tax_return_frequency'),
        );
    }

    /**
     * @return array<string, mixed>
     */
    public function toArray(): array
    {
        return array_filter([
            'functional_currency_code' => $this->functionalCurrencyCode,
            'fiscal_year_start_month' => $this->fiscalYearStartMonth,
            'retained_earnings_account_id' => $this->retainedEarningsAccountId,
            'current_year_earnings_account_id' => $this->currentYearEarningsAccountId,
            'opening_balance_equity_account_id' => $this->openingBalanceEquityAccountId,
            'suspense_account_id' => $this->suspenseAccountId,
            'rounding_account_id' => $this->roundingAccountId,
            'fx_gain_account_id' => $this->fxGainAccountId,
            'fx_loss_account_id' => $this->fxLossAccountId,
            'default_inventory_valuation_method' => $this->defaultInventoryValuationMethod?->value,
            'negative_inventory_policy' => $this->negativeInventoryPolicy,
            'allow_manual_journal_posting' => $this->allowManualJournalPosting,
            'manual_journal_approval_limit' => $this->manualJournalApprovalLimit,
            'backdated_posting_policy' => $this->backdatedPostingPolicy,
            'backdated_entry_approval_required' => $this->backdatedEntryApprovalRequired,
            'zero_cost_inventory_policy' => $this->zeroCostInventoryPolicy,
            'fiscal_year_close_approval_required' => $this->fiscalYearCloseApprovalRequired,
            'period_lock_mode' => $this->periodLockMode,
            'journal_numbering_mode' => $this->journalNumberingMode,
            'accounting_cutover_date' => $this->accountingCutoverDate,
            'fiscal_year_reopen_window_hours' => $this->fiscalYearReopenWindowHours,
            'default_sales_tax_type_id' => $this->defaultSalesTaxTypeId,
            'default_purchase_tax_type_id' => $this->defaultPurchaseTaxTypeId,
            'tax_reporting_enabled' => $this->taxReportingEnabled,
            'tax_return_frequency' => $this->taxReturnFrequency,
        ], fn (mixed $value) => $value !== null);
    }
}
