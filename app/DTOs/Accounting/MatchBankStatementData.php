<?php

declare(strict_types=1);

namespace App\DTOs\Accounting;

use App\Http\Requests\Admin\Accounting\MatchBankStatementLineRequest;

final readonly class MatchBankStatementData
{
    public function __construct(
        public int $journalTransactionId,
        public ?float $matchedAmount,
    ) {}

    public static function fromRequest(MatchBankStatementLineRequest $request): self
    {
        return new self(
            journalTransactionId: (int) $request->validated('journal_transaction_id'),
            matchedAmount: $request->validated('matched_amount') !== null
                ? (float) $request->validated('matched_amount') : null,
        );
    }
}
