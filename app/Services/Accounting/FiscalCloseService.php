<?php

declare(strict_types=1);

namespace App\Services\Accounting;

use App\Enums\FiscalYearStatus;
use App\Enums\JournalEntryStatus;
use App\Models\ChartOfAccount;
use App\Models\FinancialSetting;
use App\Models\FiscalYear;
use App\Models\FiscalYearReopenRequest;
use App\Models\JournalEntry;
use App\Models\JournalTransaction;
use DomainException;
use Illuminate\Support\Facades\DB;

final class FiscalCloseService
{
    public function __construct(
        private readonly FinancialSettingsService $settings,
        private readonly JournalService $journalService,
        private readonly JournalNumberService $numberService,
    ) {}

    /**
     * @return list<string>
     */
    public function validate(FiscalYear $fiscalYear): array
    {
        $errors = [];

        if (in_array($fiscalYear->status, [FiscalYearStatus::Closed, FiscalYearStatus::Reopening], true)) {
            $errors[] = 'Fiscal year is already closed or in a reopening window.';
        }

        $unposted = JournalEntry::query()
            ->where('fiscal_year_id', $fiscalYear->id)
            ->whereIn('status', [
                JournalEntryStatus::Draft,
                JournalEntryStatus::PendingApproval,
                JournalEntryStatus::Approved,
            ])
            ->count();

        if ($unposted > 0) {
            $errors[] = "{$unposted} journal(s) are not posted.";
        }

        $unbalanced = JournalEntry::query()
            ->where('fiscal_year_id', $fiscalYear->id)
            ->where('status', JournalEntryStatus::Posted)
            ->withSum('transactions as total_debit', 'debit')
            ->withSum('transactions as total_credit', 'credit')
            ->get()
            ->filter(fn (JournalEntry $entry) => bccomp(
                (string) ($entry->total_debit ?? '0'),
                (string) ($entry->total_credit ?? '0'),
                2,
            ) !== 0);

        if ($unbalanced->isNotEmpty()) {
            $errors[] = $unbalanced->count().' posted journal(s) are unbalanced.';
        }

        $settings = $this->settings->get();

        if ($settings->retained_earnings_account_id === null) {
            $errors[] = 'Retained earnings account is not configured.';
        }

        return $errors;
    }

    public function close(FiscalYear $fiscalYear, int $userId): FiscalYear
    {
        $errors = $this->validate($fiscalYear);

        if ($errors !== []) {
            throw new DomainException(implode(' ', $errors));
        }

        return DB::transaction(function () use ($fiscalYear, $userId) {
            $fiscalYear->update(['status' => FiscalYearStatus::Closing]);

            JournalEntry::query()
                ->where('fiscal_year_id', $fiscalYear->id)
                ->whereNull('locked_at')
                ->update(['locked_at' => now()]);

            $netIncome = $this->calculateNetIncome($fiscalYear);
            $settings = $this->settings->get();

            if (abs($netIncome) >= 0.01) {
                $this->postClosingJournal($fiscalYear, $netIncome, $settings, $userId);
            }

            $fiscalYear->update([
                'status' => FiscalYearStatus::Closed,
                'closed_at' => now(),
                'closed_by' => $userId,
            ]);

            return $fiscalYear->fresh();
        });
    }

    public function requestReopen(FiscalYear $fiscalYear, int $userId, string $reason): FiscalYearReopenRequest
    {
        if ($fiscalYear->status !== FiscalYearStatus::Closed) {
            throw new DomainException('Only closed fiscal years can be reopened.');
        }

        $pending = FiscalYearReopenRequest::query()
            ->where('fiscal_year_id', $fiscalYear->id)
            ->where('status', 'pending')
            ->exists();

        if ($pending) {
            throw new DomainException('A reopen request is already pending approval.');
        }

        return FiscalYearReopenRequest::query()->create([
            'fiscal_year_id' => $fiscalYear->id,
            'reason' => $reason,
            'requested_by' => $userId,
            'status' => 'pending',
        ]);
    }

    public function approveReopen(FiscalYearReopenRequest $request, int $userId): FiscalYearReopenRequest
    {
        if ($request->status !== 'pending') {
            throw new DomainException('This reopen request is no longer pending.');
        }

        if ($request->requested_by === $userId) {
            throw new DomainException('Requester cannot approve their own reopen request.');
        }

        return DB::transaction(function () use ($request, $userId) {
            if ($request->first_approved_by === null) {
                $request->update(['first_approved_by' => $userId]);

                return $request->fresh();
            }

            if ($request->first_approved_by === $userId) {
                throw new DomainException('Second approval must come from a different user.');
            }

            if ($request->second_approved_by !== null) {
                throw new DomainException('This reopen request already has dual approval.');
            }

            $settings = $this->settings->get();
            $windowHours = max(1, (int) ($settings->fiscal_year_reopen_window_hours ?? 48));
            $expiresAt = now()->addHours($windowHours);

            $request->update([
                'second_approved_by' => $userId,
                'status' => 'approved',
            ]);

            $fiscalYear = $request->fiscalYear;
            $fiscalYear->update([
                'status' => FiscalYearStatus::Reopening,
                'reopened_at' => now(),
                'reopened_by' => $userId,
                'reopen_expires_at' => $expiresAt,
            ]);

            JournalEntry::query()
                ->where('fiscal_year_id', $fiscalYear->id)
                ->whereNotNull('locked_at')
                ->update(['locked_at' => null]);

            return $request->fresh(['fiscalYear']);
        });
    }

    public function rejectReopen(FiscalYearReopenRequest $request, int $userId): FiscalYearReopenRequest
    {
        if ($request->status !== 'pending') {
            throw new DomainException('This reopen request is no longer pending.');
        }

        if ($request->requested_by === $userId) {
            throw new DomainException('Requester cannot reject their own reopen request.');
        }

        $request->update(['status' => 'rejected']);

        return $request->fresh(['fiscalYear']);
    }

    /**
     * @return list<int>
     */
    public function expireReopenedFiscalYears(): array
    {
        $expiredIds = FiscalYear::query()
            ->where('status', FiscalYearStatus::Reopening)
            ->whereNotNull('reopen_expires_at')
            ->where('reopen_expires_at', '<', now())
            ->pluck('id')
            ->all();

        foreach ($expiredIds as $fiscalYearId) {
            DB::transaction(function () use ($fiscalYearId) {
                $fiscalYear = FiscalYear::query()->findOrFail($fiscalYearId);

                $fiscalYear->update([
                    'status' => FiscalYearStatus::Closed,
                    'reopen_expires_at' => null,
                ]);

                JournalEntry::query()
                    ->where('fiscal_year_id', $fiscalYear->id)
                    ->whereNull('locked_at')
                    ->update(['locked_at' => now()]);
            });
        }

        return $expiredIds;
    }

    private function calculateNetIncome(FiscalYear $fiscalYear): float
    {
        $revenue = $this->sumByAccountType($fiscalYear, 'revenue');
        $expense = $this->sumByAccountType($fiscalYear, 'expense');

        return round(-$revenue - $expense, 2);
    }

    private function sumByAccountType(FiscalYear $fiscalYear, string $type): float
    {
        $result = JournalTransaction::query()
            ->join('journal_entries', 'journal_entries.id', '=', 'journal_transactions.journal_entry_id')
            ->join('chart_of_accounts', 'chart_of_accounts.id', '=', 'journal_transactions.account_id')
            ->where('journal_entries.fiscal_year_id', $fiscalYear->id)
            ->where('journal_entries.status', JournalEntryStatus::Posted)
            ->where('journal_entries.is_closing_entry', false)
            ->where('chart_of_accounts.type', $type)
            ->selectRaw('COALESCE(SUM(journal_transactions.debit), 0) - COALESCE(SUM(journal_transactions.credit), 0) as balance')
            ->value('balance');

        return (float) $result;
    }

    private function postClosingJournal(FiscalYear $fiscalYear, float $netIncome, FinancialSetting $settings, int $userId): void
    {
        $retainedEarningsId = (int) $settings->retained_earnings_account_id;
        $currentYearEarningsId = $settings->current_year_earnings_account_id
            ? (int) $settings->current_year_earnings_account_id
            : $this->resolveCurrentYearEarningsAccount();

        $description = "Fiscal year close — {$fiscalYear->name}";
        $amount = abs($netIncome);

        if ($netIncome > 0) {
            $lines = [
                ['account_id' => $currentYearEarningsId, 'debit' => $amount, 'credit' => 0, 'description' => $description],
                ['account_id' => $retainedEarningsId, 'debit' => 0, 'credit' => $amount, 'description' => $description],
            ];
        } else {
            $lines = [
                ['account_id' => $retainedEarningsId, 'debit' => $amount, 'credit' => 0, 'description' => $description],
                ['account_id' => $currentYearEarningsId, 'debit' => 0, 'credit' => $amount, 'description' => $description],
            ];
        }

        $entry = $this->journalService->createDraft([
            'journal_date' => $fiscalYear->end_date->toDateString(),
            'fiscal_year_id' => $fiscalYear->id,
            'legal_entity_id' => $fiscalYear->legal_entity_id,
            'description' => $description,
            'reference' => 'FY-CLOSE-'.$fiscalYear->id,
            'is_system_generated' => true,
            'is_closing_entry' => true,
            'source_module' => 'accounting',
            'source_event' => 'fiscal_year.closed',
            'journal_number' => $this->numberService->next(null, $fiscalYear->id),
        ], $lines, $userId);

        $this->journalService->post($entry, $userId);
    }

    private function resolveCurrentYearEarningsAccount(): int
    {
        $account = ChartOfAccount::query()
            ->where('type', 'equity')
            ->where(function ($q) {
                $q->where('code', 'like', '%3200%')
                    ->orWhere('name', 'like', '%current year%');
            })
            ->where('is_postable', true)
            ->first();

        if ($account === null) {
            throw new DomainException('Current year earnings account is not configured.');
        }

        return $account->id;
    }
}
