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
        public ?bool $allowNegativeInventory,
        public ?bool $allowManualJournalPosting,
        public ?float $manualJournalApprovalLimit,
        public ?string $backdatedPostingPolicy,
        public ?bool $backdatedEntryApprovalRequired,
        public ?bool $fiscalYearCloseApprovalRequired,
        public ?string $periodLockMode,
        public ?string $journalNumberingMode,
        public ?string $accountingCutoverDate,
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
            allowNegativeInventory: $request->has('allow_negative_inventory') ? $request->boolean('allow_negative_inventory') : null,
            allowManualJournalPosting: $request->has('allow_manual_journal_posting') ? $request->boolean('allow_manual_journal_posting') : null,
            manualJournalApprovalLimit: $request->validated('manual_journal_approval_limit') !== null
                ? (float) $request->validated('manual_journal_approval_limit') : null,
            backdatedPostingPolicy: $request->validated('backdated_posting_policy'),
            backdatedEntryApprovalRequired: $request->has('backdated_entry_approval_required')
                ? $request->boolean('backdated_entry_approval_required') : null,
            fiscalYearCloseApprovalRequired: $request->has('fiscal_year_close_approval_required')
                ? $request->boolean('fiscal_year_close_approval_required') : null,
            periodLockMode: $request->validated('period_lock_mode'),
            journalNumberingMode: $request->validated('journal_numbering_mode'),
            accountingCutoverDate: $request->validated('accounting_cutover_date'),
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
            'allow_negative_inventory' => $this->allowNegativeInventory,
            'allow_manual_journal_posting' => $this->allowManualJournalPosting,
            'manual_journal_approval_limit' => $this->manualJournalApprovalLimit,
            'backdated_posting_policy' => $this->backdatedPostingPolicy,
            'backdated_entry_approval_required' => $this->backdatedEntryApprovalRequired,
            'fiscal_year_close_approval_required' => $this->fiscalYearCloseApprovalRequired,
            'period_lock_mode' => $this->periodLockMode,
            'journal_numbering_mode' => $this->journalNumberingMode,
            'accounting_cutover_date' => $this->accountingCutoverDate,
        ], fn (mixed $value) => $value !== null);
    }
}
