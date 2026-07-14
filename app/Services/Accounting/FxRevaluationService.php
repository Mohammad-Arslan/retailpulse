<?php

declare(strict_types=1);

namespace App\Services\Accounting;

use App\Enums\ChartOfAccountType;
use App\Enums\ExchangeRateType;
use App\Enums\JournalEntryStatus;
use App\Models\ChartOfAccount;
use App\Models\JournalEntry;
use App\Models\JournalTransaction;
use Carbon\CarbonInterface;
use DomainException;
use Illuminate\Support\Collection;

final class FxRevaluationService
{
    public function __construct(
        private readonly CurrencyConversionService $currencyConversion,
        private readonly JournalService $journalService,
        private readonly FinancialSettingsService $settings,
    ) {}

    /**
     * @return array{revaluation_entry: JournalEntry, reversal_entry: JournalEntry, lines: array<int, array<string, mixed>>}
     */
    public function revalue(
        CarbonInterface $asOfDate,
        int $userId,
        ?int $branchId = null,
        ?int $legalEntityId = null,
    ): array {
        $this->assertNotAlreadyRevalued($asOfDate);

        $functionalCurrency = $this->currencyConversion->functionalCurrencyCode();
        $candidates = $this->candidateAccounts($functionalCurrency, $branchId, $legalEntityId);

        $journalLines = [];
        $detailLines = [];
        $totalGain = 0.0;
        $totalLoss = 0.0;

        foreach ($candidates as $account) {
            $balances = $this->accountBalances($account, $asOfDate, $branchId, $legalEntityId);

            if ($balances === null) {
                continue;
            }

            [$netTransactionBalance, $bookedFunctional] = $balances;

            $periodEndRate = $this->currencyConversion->resolveRate(
                $account->currency_code,
                $asOfDate,
                ExchangeRateType::Closing,
            );
            $revaluedFunctional = round($netTransactionBalance * $periodEndRate, 2);
            $delta = round($revaluedFunctional - $bookedFunctional, 2);

            if (abs($delta) < 0.005) {
                continue;
            }

            $journalLines[] = [
                'account_id' => $account->id,
                'debit' => $delta > 0 ? abs($delta) : 0,
                'credit' => $delta > 0 ? 0 : abs($delta),
                'transaction_currency_amount' => 0,
                'currency_code' => $account->currency_code,
                'exchange_rate' => $periodEndRate,
                'branch_id' => $branchId,
                'description' => "FX revaluation: {$account->code} @ {$periodEndRate} as of {$asOfDate->toDateString()}",
            ];

            if ($delta > 0) {
                $totalGain += $delta;
            } else {
                $totalLoss += abs($delta);
            }

            $detailLines[] = [
                'account_id' => $account->id,
                'account_code' => $account->code,
                'currency_code' => $account->currency_code,
                'net_transaction_balance' => $netTransactionBalance,
                'booked_functional' => $bookedFunctional,
                'revalued_functional' => $revaluedFunctional,
                'delta' => $delta,
                'direction' => $delta > 0 ? 'gain' : 'loss',
            ];
        }

        if ($journalLines === []) {
            throw new DomainException("No foreign-currency balances required revaluation as of {$asOfDate->toDateString()}.");
        }

        $settings = $this->settings->get();

        if ($totalGain > 0) {
            if ($settings->fx_gain_account_id === null) {
                throw new DomainException('FX gain account is not configured in financial settings.');
            }

            $journalLines[] = [
                'account_id' => (int) $settings->fx_gain_account_id,
                'debit' => 0,
                'credit' => round($totalGain, 2),
                'description' => "FX revaluation gain as of {$asOfDate->toDateString()}",
            ];
        }

        if ($totalLoss > 0) {
            if ($settings->fx_loss_account_id === null) {
                throw new DomainException('FX loss account is not configured in financial settings.');
            }

            $journalLines[] = [
                'account_id' => (int) $settings->fx_loss_account_id,
                'debit' => round($totalLoss, 2),
                'credit' => 0,
                'description' => "FX revaluation loss as of {$asOfDate->toDateString()}",
            ];
        }

        $revaluationEntry = $this->journalService->createDraft([
            'journal_date' => $asOfDate->toDateString(),
            'branch_id' => $branchId,
            'legal_entity_id' => $legalEntityId,
            'description' => "FX revaluation as of {$asOfDate->toDateString()}",
            'source_module' => 'accounting',
            'source_event' => 'fx_revaluation',
            'is_system_generated' => true,
        ], $journalLines, $userId);

        $revaluationEntry = $this->journalService->post($revaluationEntry, $userId);

        $reversalEntry = $this->journalService->reverse(
            $revaluationEntry,
            $userId,
            "Reversal of FX revaluation as of {$asOfDate->toDateString()}",
            $asOfDate->copy()->addDay(),
        );

        return [
            'revaluation_entry' => $revaluationEntry,
            'reversal_entry' => $reversalEntry,
            'lines' => $detailLines,
        ];
    }

    private function assertNotAlreadyRevalued(CarbonInterface $asOfDate): void
    {
        // No status filter: revalue() reverses the revaluation entry itself immediately
        // after posting it, so by the time a second call could run, the first call's
        // entry is already Reversed — excluding that status here would make this guard
        // a no-op. Any existing row for this date, in any status, means "already run".
        $exists = JournalEntry::query()
            ->where('source_event', 'fx_revaluation')
            ->whereDate('journal_date', $asOfDate->toDateString())
            ->exists();

        if ($exists) {
            throw new DomainException("FX revaluation already posted for {$asOfDate->toDateString()}.");
        }
    }

    /**
     * @return Collection<int, ChartOfAccount>
     */
    private function candidateAccounts(string $functionalCurrency, ?int $branchId, ?int $legalEntityId): Collection
    {
        return ChartOfAccount::query()
            ->whereNotNull('currency_code')
            ->where('currency_code', '!=', $functionalCurrency)
            ->whereIn('type', [ChartOfAccountType::Asset, ChartOfAccountType::Liability])
            ->where('is_postable', true)
            ->where('status', 'active')
            ->when($legalEntityId, fn ($q) => $q->where(fn ($q2) => $q2->whereNull('legal_entity_id')->orWhere('legal_entity_id', $legalEntityId)))
            ->when($branchId, fn ($q) => $q->where(fn ($q2) => $q2->whereNull('branch_id')->orWhere('branch_id', $branchId)))
            ->get();
    }

    /**
     * @return array{0: float, 1: float}|null
     */
    private function accountBalances(ChartOfAccount $account, CarbonInterface $asOfDate, ?int $branchId, ?int $legalEntityId): ?array
    {
        $rows = JournalTransaction::query()
            ->join('journal_entries', 'journal_entries.id', '=', 'journal_transactions.journal_entry_id')
            ->whereIn('journal_entries.status', [
                JournalEntryStatus::Posted,
                JournalEntryStatus::Reversed,
            ])
            ->where('journal_transactions.account_id', $account->id)
            ->whereDate('journal_entries.journal_date', '<=', $asOfDate->toDateString())
            ->when($branchId, fn ($q) => $q->where('journal_entries.branch_id', $branchId))
            ->when($legalEntityId, fn ($q) => $q->where('journal_entries.legal_entity_id', $legalEntityId))
            ->get([
                'journal_transactions.debit',
                'journal_transactions.credit',
                'journal_transactions.transaction_currency_amount',
                'journal_transactions.functional_currency_amount',
            ]);

        if ($rows->isEmpty()) {
            return null;
        }

        $netTransactionBalance = $rows->sum(fn ($row) => (float) $row->debit > 0
            ? (float) ($row->transaction_currency_amount ?? 0)
            : -(float) ($row->transaction_currency_amount ?? 0));

        $bookedFunctional = $rows->sum(fn ($row) => (float) $row->functional_currency_amount);

        return [$netTransactionBalance, $bookedFunctional];
    }
}
