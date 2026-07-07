<?php

declare(strict_types=1);

namespace App\Services\Accounting;

use App\Enums\BankReconciliationMatchType;
use App\Models\BankStatementLine;
use App\Models\JournalTransaction;
use App\Repositories\Contracts\BankAccountRepositoryInterface;
use App\Support\BankAccountPresenter;

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
                ->map(fn (BankStatementLine $line) => BankAccountPresenter::statementLine($line))
                ->values()
            : collect();

        $suggestions = $bankAccount
            ? $this->reconciliation->suggestMatches($bankAccount)->map(fn (array $row) => [
                'statement_line_id' => $row['statement_line']->id,
                'journal_transaction_id' => $row['journal_transaction']->id,
                'score' => $row['score'],
                'reference' => $row['statement_line']->reference,
                'amount' => number_format(abs($row['statement_line']->signedAmount()), 2, '.', ''),
            ])
            : collect();

        return [
            'bankAccounts' => $this->bankAccountRepository->selectOptions(),
            'selectedBankAccountId' => $bankAccount?->id,
            'statementLines' => $lines,
            'suggestions' => $suggestions,
        ];
    }

    public function match(
        BankStatementLine $statementLine,
        int $journalTransactionId,
        ?float $matchedAmount,
        int $userId,
    ): void {
        $transaction = JournalTransaction::query()->findOrFail($journalTransactionId);

        $this->reconciliation->match(
            $statementLine,
            $transaction,
            $userId,
            $matchedAmount,
            BankReconciliationMatchType::OneToOne,
        );
    }
}
