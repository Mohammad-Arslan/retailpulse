<?php

declare(strict_types=1);

namespace App\Services\Accounting;

use App\DTOs\Accounting\MatchBankStatementData;
use App\Enums\BankReconciliationMatchType;
use App\Models\BankStatementLine;
use App\Models\JournalTransaction;
use App\Repositories\Contracts\BankAccountRepositoryInterface;
use App\Support\BankAccountPresenter;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

final class BankReconciliationPageService
{
    public function __construct(
        private readonly BankAccountRepositoryInterface $bankAccountRepository,
        private readonly BankReconciliationService $reconciliation,
    ) {}

    /**
     * @return array<string, mixed>
     */
    public function indexPayload(?int $bankAccountId): array
    {
        $resolvedId = $bankAccountId ?: $this->bankAccountRepository->firstId();
        $bankAccount = $resolvedId ? $this->bankAccountRepository->findById($resolvedId) : null;

        $lines = $bankAccount
            ? $this->bankAccountRepository->recentStatementLines($bankAccount->id)
                ->load('matches')
                ->map(fn (BankStatementLine $line) => BankAccountPresenter::statementLine(
                    $line,
                    $this->reconciliation->matchedAmountTotal($line),
                    $this->reconciliation->remainingAmount($line),
                ))
                ->values()
            : collect();

        $suggestions = $bankAccount
            ? $this->reconciliation->suggestMatches($bankAccount)->map(fn (array $row) => [
                'statement_line_id' => $row['statement_line']->id,
                'journal_transaction_id' => $row['journal_transaction']->id,
                'journal_reference' => $row['journal_transaction']->journalEntry?->journal_number
                    ?? $row['journal_transaction']->description,
                'journal_amount' => number_format(
                    max((float) $row['journal_transaction']->debit, (float) $row['journal_transaction']->credit),
                    2,
                    '.',
                    '',
                ),
                'score' => $row['score'],
                'reference' => $row['statement_line']->reference,
                'amount' => number_format(abs($row['statement_line']->signedAmount()), 2, '.', ''),
                'remaining_amount' => number_format(
                    $this->reconciliation->remainingAmount($row['statement_line']),
                    2,
                    '.',
                    '',
                ),
            ])
            : collect();

        $matchableTransactions = $bankAccount
            ? JournalTransaction::query()
                ->select('journal_transactions.*')
                ->with('journalEntry:id,journal_number,journal_date')
                ->join('journal_entries', 'journal_entries.id', '=', 'journal_transactions.journal_entry_id')
                ->where('journal_entries.status', \App\Enums\JournalEntryStatus::Posted)
                ->where('journal_transactions.account_id', $bankAccount->coa_account_id)
                ->orderByDesc('journal_entries.journal_date')
                ->limit(100)
                ->get()
                ->map(fn (JournalTransaction $tx) => [
                    'id' => $tx->id,
                    'label' => trim(sprintf(
                        '%s — %s — %s',
                        $tx->journalEntry?->journal_number ?? ('#'.$tx->journal_entry_id),
                        $tx->journalEntry?->journal_date?->toDateString() ?? '',
                        number_format(max((float) $tx->debit, (float) $tx->credit), 2, '.', ''),
                    )),
                    'amount' => number_format(max((float) $tx->debit, (float) $tx->credit), 2, '.', ''),
                ])
                ->values()
            : collect();

        return [
            'bankAccounts' => $this->bankAccountRepository->selectOptions(),
            'selectedBankAccountId' => $bankAccount?->id,
            'statementLines' => $lines,
            'suggestions' => $suggestions,
            'matchableTransactions' => $matchableTransactions,
        ];
    }

    public function match(
        BankStatementLine $statementLine,
        MatchBankStatementData $data,
        int $userId,
    ): void {
        $pairs = $data->pairs;
        $remaining = $this->reconciliation->remainingAmount($statementLine);
        $requestedTotal = 0.0;
        $resolvedPairs = [];

        foreach ($pairs as $pair) {
            $transaction = JournalTransaction::query()->findOrFail($pair['journal_transaction_id']);
            $journalAmount = max((float) $transaction->debit, (float) $transaction->credit);
            $amount = $pair['matched_amount'] !== null
                ? (float) $pair['matched_amount']
                : min($remaining - $requestedTotal, $journalAmount);

            $resolvedPairs[] = [
                'journal_transaction_id' => (int) $pair['journal_transaction_id'],
                'matched_amount' => round($amount, 2),
                'transaction' => $transaction,
            ];
            $requestedTotal += round($amount, 2);
        }

        if (round($requestedTotal, 2) - $remaining > 0.001) {
            throw ValidationException::withMessages([
                'matched_amount' => __('Matched amount exceeds the remaining statement line balance of :remaining.', [
                    'remaining' => number_format($remaining, 2, '.', ''),
                ]),
            ]);
        }

        $pairCount = count($resolvedPairs);
        $willCoverFully = round($requestedTotal, 2) + 0.001 >= $remaining;

        $matchType = match (true) {
            $pairCount > 1 && ! $willCoverFully => BankReconciliationMatchType::Partial,
            $pairCount > 1 => BankReconciliationMatchType::OneToMany,
            ! $willCoverFully => BankReconciliationMatchType::Partial,
            default => BankReconciliationMatchType::OneToOne,
        };

        DB::transaction(function () use ($statementLine, $resolvedPairs, $userId, $matchType) {
            foreach ($resolvedPairs as $pair) {
                $this->reconciliation->match(
                    $statementLine->fresh(['matches']) ?? $statementLine,
                    $pair['transaction'],
                    $userId,
                    $pair['matched_amount'],
                    $matchType,
                );
            }
        });
    }
}
