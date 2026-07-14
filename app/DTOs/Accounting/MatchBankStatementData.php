<?php

declare(strict_types=1);

namespace App\DTOs\Accounting;

use App\Http\Requests\Admin\Accounting\MatchBankStatementLineRequest;

final readonly class MatchBankStatementData
{
    /**
     * @param  list<array{journal_transaction_id: int, matched_amount: float|null}>  $pairs
     */
    public function __construct(
        public array $pairs,
    ) {}

    public static function fromRequest(MatchBankStatementLineRequest $request): self
    {
        $validated = $request->validated();

        if (! empty($validated['transactions']) && is_array($validated['transactions'])) {
            $pairs = array_map(
                static fn (array $row): array => [
                    'journal_transaction_id' => (int) $row['journal_transaction_id'],
                    'matched_amount' => isset($row['matched_amount']) ? (float) $row['matched_amount'] : null,
                ],
                $validated['transactions'],
            );

            return new self(array_values($pairs));
        }

        return new self([
            [
                'journal_transaction_id' => (int) $validated['journal_transaction_id'],
                'matched_amount' => isset($validated['matched_amount'])
                    ? (float) $validated['matched_amount']
                    : null,
            ],
        ]);
    }
}
