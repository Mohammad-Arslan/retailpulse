<?php

declare(strict_types=1);

namespace App\Services\Accounting;

use App\Enums\FiscalYearStatus;
use App\Enums\JournalEntryStatus;
use App\Models\FiscalYear;
use App\Models\JournalEntry;
use DomainException;

final class JournalValidationService
{
    public function __construct(
        private readonly FinancialSettingsService $settings,
    ) {}

    public function assertCanPost(JournalEntry $entry): void
    {
        if ($entry->status->isImmutable()) {
            throw new DomainException('Journal entry is already posted or reversed.');
        }

        if ($entry->status === JournalEntryStatus::PendingApproval) {
            throw new DomainException('Journal entry is pending approval.');
        }

        $entry->loadMissing('transactions');

        $totalDebit = $entry->transactions->sum('debit');
        $totalCredit = $entry->transactions->sum('credit');

        if (round((float) $totalDebit, 2) !== round((float) $totalCredit, 2)) {
            throw new DomainException('Journal entry is not balanced.');
        }

        if ($entry->transactions->isEmpty()) {
            throw new DomainException('Journal entry has no lines.');
        }

        $this->assertPeriodOpen($entry);
    }

    public function assertCanEdit(JournalEntry $entry): void
    {
        if ($entry->status->isImmutable()) {
            throw new DomainException('Posted journals cannot be edited.');
        }
    }

    public function assertCanReverse(JournalEntry $entry): void
    {
        if ($entry->status !== JournalEntryStatus::Posted) {
            throw new DomainException('Only posted journals can be reversed.');
        }

        $this->assertPeriodOpen($entry);
    }

    private function assertPeriodOpen(JournalEntry $entry): void
    {
        if ($entry->fiscal_year_id === null) {
            return;
        }

        $fiscalYear = FiscalYear::query()->find($entry->fiscal_year_id);

        if ($fiscalYear && $fiscalYear->status === FiscalYearStatus::Closed) {
            throw new DomainException('Cannot post to a closed fiscal period.');
        }

        $settings = $this->settings->get();

        if ($settings->accounting_cutover_date && $entry->journal_date->lt($settings->accounting_cutover_date)) {
            throw new DomainException('Journal date is before accounting cutover date.');
        }
    }
}
