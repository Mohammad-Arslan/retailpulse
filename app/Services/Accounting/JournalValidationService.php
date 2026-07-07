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

        $this->assertJournalBalanced($entry);
        $this->assertPeriodOpen($entry);
    }

    public function assertJournalBalanced(JournalEntry $entry): void
    {
        $entry->loadMissing('transactions');

        $lineCount = $entry->transactions->count();

        if ($lineCount === 0) {
            throw new DomainException(sprintf(
                'Journal %s dated %s has no lines and cannot be posted.',
                $entry->journal_number,
                $entry->journal_date->toDateString(),
            ));
        }

        $totalDebit = $this->sumDecimalColumn($entry, 'debit');
        $totalCredit = $this->sumDecimalColumn($entry, 'credit');

        if (bccomp($totalDebit, $totalCredit, 2) !== 0) {
            $difference = bcsub($totalDebit, $totalCredit, 2);

            throw new DomainException(sprintf(
                'Journal %s dated %s is not balanced. Total debits: %s, total credits: %s, difference: %s, line count: %d.',
                $entry->journal_number,
                $entry->journal_date->toDateString(),
                $this->formatDecimal($totalDebit),
                $this->formatDecimal($totalCredit),
                $this->formatDecimal($difference),
                $lineCount,
            ));
        }
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

    public function assertFiscalYearOpen(JournalEntry $entry): void
    {
        $fiscalYear = $this->resolveFiscalYear($entry);

        if ($fiscalYear === null) {
            return;
        }

        if (in_array($fiscalYear->status, [FiscalYearStatus::Open, FiscalYearStatus::Closing], true)) {
            return;
        }

        if ($fiscalYear->status === FiscalYearStatus::Reopening) {
            if ($fiscalYear->reopen_expires_at !== null && $fiscalYear->reopen_expires_at->isPast()) {
                throw $this->closedFiscalYearException($fiscalYear, 'Reopening window has expired.');
            }

            return;
        }

        throw $this->closedFiscalYearException($fiscalYear);
    }

    private function assertPeriodOpen(JournalEntry $entry): void
    {
        $this->assertFiscalYearOpen($entry);

        $settings = $this->settings->get();

        if ($settings->accounting_cutover_date && $entry->journal_date->lt($settings->accounting_cutover_date)) {
            throw new DomainException('Journal date is before accounting cutover date.');
        }
    }

    private function resolveFiscalYear(JournalEntry $entry): ?FiscalYear
    {
        if ($entry->fiscal_year_id !== null) {
            return FiscalYear::query()
                ->with('closedByUser')
                ->find($entry->fiscal_year_id);
        }

        return FiscalYear::query()
            ->with('closedByUser')
            ->whereDate('start_date', '<=', $entry->journal_date)
            ->whereDate('end_date', '>=', $entry->journal_date)
            ->orderByDesc('start_date')
            ->first();
    }

    private function closedFiscalYearException(FiscalYear $fiscalYear, ?string $suffix = null): DomainException
    {
        $fiscalYear->loadMissing('closedByUser');

        $closedDate = $fiscalYear->closed_at?->toDateTimeString() ?? 'unknown';
        $closedBy = $fiscalYear->closedByUser?->name ?? 'unknown';
        $message = sprintf(
            'Cannot post to %s (status: %s). Fiscal year closed on %s by %s.',
            $fiscalYear->name,
            $fiscalYear->status->value,
            $closedDate,
            $closedBy,
        );

        if ($suffix !== null) {
            $message .= ' '.$suffix;
        }

        return new DomainException($message);
    }

    private function sumDecimalColumn(JournalEntry $entry, string $column): string
    {
        $total = '0.00';

        foreach ($entry->transactions as $transaction) {
            $total = bcadd($total, number_format((float) $transaction->{$column}, 2, '.', ''), 2);
        }

        return $total;
    }

    private function formatDecimal(string $amount): string
    {
        return number_format((float) $amount, 2, '.', '');
    }
}
