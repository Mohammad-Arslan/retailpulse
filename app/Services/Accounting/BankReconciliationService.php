<?php

declare(strict_types=1);

namespace App\Services\Accounting;

use App\Enums\BankReconciliationMatchType;
use App\Enums\BankStatementLineStatus;
use App\Enums\JournalEntryStatus;
use App\Models\BankAccount;
use App\Models\BankReconciliationMatch;
use App\Models\BankStatementLine;
use App\Models\JournalTransaction;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

final class BankReconciliationService
{
    public function __construct(
        private readonly DocumentNumberService $documentNumbers,
    ) {}

    /**
     * @return array{imported: int, skipped: int, batch_id: string}
     */
    public function importCsv(BankAccount $bankAccount, UploadedFile $file): array
    {
        $batchId = $this->documentNumbers->importBatchId();
        $handle = fopen($file->getRealPath(), 'r');

        if ($handle === false) {
            throw ValidationException::withMessages([
                'file' => __('Could not read the uploaded file.'),
            ]);
        }

        $header = fgetcsv($handle);
        $imported = 0;
        $skipped = 0;

        while (($row = fgetcsv($handle)) !== false) {
            if (count($row) < 4) {
                $skipped++;

                continue;
            }

            [$date, $reference, $description, $amount] = [
                $row[0] ?? '',
                $row[1] ?? null,
                $row[2] ?? null,
                (float) ($row[3] ?? 0),
            ];

            $transactionDate = date('Y-m-d', strtotime($date));

            $duplicate = BankStatementLine::query()
                ->where('bank_account_id', $bankAccount->id)
                ->whereDate('transaction_date', $transactionDate)
                ->where('reference', $reference)
                ->where(function ($q) use ($amount) {
                    if ($amount >= 0) {
                        $q->where('debit', $amount);
                    } else {
                        $q->where('credit', abs($amount));
                    }
                })
                ->exists();

            if ($duplicate) {
                $skipped++;

                continue;
            }

            BankStatementLine::query()->create([
                'bank_account_id' => $bankAccount->id,
                'statement_date' => $transactionDate,
                'transaction_date' => $transactionDate,
                'reference' => $reference,
                'description' => $description,
                'debit' => $amount > 0 ? $amount : 0,
                'credit' => $amount < 0 ? abs($amount) : 0,
                'import_batch_id' => $batchId,
                'status' => BankStatementLineStatus::Unmatched,
            ]);

            $imported++;
        }

        fclose($handle);

        return [
            'imported' => $imported,
            'skipped' => $skipped,
            'batch_id' => $batchId,
        ];
    }

    /**
     * @return Collection<int, array{statement_line: BankStatementLine, journal_transaction: JournalTransaction, score: int}>
     */
    public function suggestMatches(BankAccount $bankAccount, int $limit = 25): Collection
    {
        $lines = BankStatementLine::query()
            ->where('bank_account_id', $bankAccount->id)
            ->whereIn('status', [BankStatementLineStatus::Unmatched, BankStatementLineStatus::Suggested])
            ->orderByDesc('transaction_date')
            ->limit($limit)
            ->get();

        $suggestions = collect();

        foreach ($lines as $line) {
            $amount = abs($line->signedAmount());
            $candidates = JournalTransaction::query()
                ->select('journal_transactions.*')
                ->join('journal_entries', 'journal_entries.id', '=', 'journal_transactions.journal_entry_id')
                ->join('chart_of_accounts', 'chart_of_accounts.id', '=', 'journal_transactions.account_id')
                ->where('journal_entries.status', JournalEntryStatus::Posted)
                ->where('chart_of_accounts.id', $bankAccount->coa_account_id)
                ->whereDate('journal_entries.journal_date', '>=', $line->transaction_date->copy()->subDays(7))
                ->whereDate('journal_entries.journal_date', '<=', $line->transaction_date->copy()->addDays(7))
                ->get();

            foreach ($candidates as $candidate) {
                $journalAmount = max((float) $candidate->debit, (float) $candidate->credit);

                if (abs($journalAmount - $amount) > 0.01) {
                    continue;
                }

                $score = 50;

                if ($line->reference && Str::contains((string) $candidate->description, (string) $line->reference, true)) {
                    $score += 30;
                }

                if ($candidate->journalEntry?->journal_date?->toDateString() === $line->transaction_date->toDateString()) {
                    $score += 20;
                }

                $suggestions->push([
                    'statement_line' => $line,
                    'journal_transaction' => $candidate,
                    'score' => $score,
                ]);

                $line->update(['status' => BankStatementLineStatus::Suggested]);
            }
        }

        return $suggestions->sortByDesc('score')->values();
    }

    public function match(
        BankStatementLine $line,
        JournalTransaction $transaction,
        int $userId,
        ?float $matchedAmount = null,
        BankReconciliationMatchType $matchType = BankReconciliationMatchType::OneToOne,
    ): BankReconciliationMatch {
        $amount = $matchedAmount ?? min(
            abs($line->signedAmount()),
            max((float) $transaction->debit, (float) $transaction->credit),
        );

        return DB::transaction(function () use ($line, $transaction, $userId, $amount, $matchType) {
            $match = BankReconciliationMatch::query()->create([
                'bank_statement_line_id' => $line->id,
                'journal_transaction_id' => $transaction->id,
                'matched_amount' => $amount,
                'match_type' => $matchType,
                'matched_by' => $userId,
                'matched_at' => now(),
            ]);

            $line->update(['status' => BankStatementLineStatus::Matched]);

            return $match;
        });
    }

    public function ignoreLine(BankStatementLine $line): BankStatementLine
    {
        $line->update(['status' => BankStatementLineStatus::Ignored]);

        return $line->fresh();
    }
}
