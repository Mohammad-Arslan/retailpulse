<?php

declare(strict_types=1);

namespace App\Support;

use App\Models\BankAccount;
use App\Models\BankStatementLine;

final class BankAccountPresenter
{
    /**
     * @return array<string, mixed>
     */
    public static function forList(BankAccount $account): array
    {
        return [
            'id' => $account->id,
            'bank_name' => $account->bank_name,
            'account_title' => $account->account_title,
            'account_number_masked' => $account->account_number_masked,
            'branch_name' => $account->branch?->name,
            'coa_account' => $account->coaAccount ? "{$account->coaAccount->code} — {$account->coaAccount->name}" : null,
            'currency_code' => $account->currency_code,
            'status' => $account->status,
        ];
    }

    /**
     * @return array<string, mixed>
     */
    /**
     * @return array<string, mixed>
     */
    public static function statementLine(
        BankStatementLine $line,
        ?float $matchedAmount = null,
        ?float $remainingAmount = null,
    ): array {
        $lineAmount = abs($line->signedAmount());
        $matched = $matchedAmount ?? (float) $line->matches->sum('matched_amount');
        $remaining = $remainingAmount ?? max(0, $lineAmount - $matched);

        return [
            'id' => $line->id,
            'transaction_date' => $line->transaction_date?->toDateString(),
            'reference' => $line->reference,
            'description' => $line->description,
            'debit' => number_format((float) $line->debit, 2, '.', ''),
            'credit' => number_format((float) $line->credit, 2, '.', ''),
            'status' => $line->status->value,
            'matched_amount' => number_format($matched, 2, '.', ''),
            'remaining_amount' => number_format($remaining, 2, '.', ''),
            'line_amount' => number_format($lineAmount, 2, '.', ''),
        ];
    }
}
