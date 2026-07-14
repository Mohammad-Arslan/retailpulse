<?php

declare(strict_types=1);

namespace App\DTOs\Accounting;

use App\Http\Requests\Admin\Accounting\StoreJournalEntryRequest;
use App\Http\Requests\Admin\Accounting\UpdateJournalEntryRequest;

final readonly class CreateJournalEntryData
{
    /**
     * @param  list<JournalEntryLineData>  $lines
     */
    public function __construct(
        public string $journalDate,
        public ?int $branchId,
        public ?int $legalEntityId,
        public ?int $fiscalYearId,
        public ?string $reference,
        public ?string $description,
        public array $lines,
    ) {}

    public static function fromRequest(StoreJournalEntryRequest|UpdateJournalEntryRequest $request): self
    {
        $lines = array_map(
            fn (array $line) => JournalEntryLineData::fromArray($line),
            $request->validated('lines'),
        );

        return new self(
            journalDate: $request->validated('journal_date'),
            branchId: $request->validated('branch_id') !== null ? (int) $request->validated('branch_id') : null,
            legalEntityId: $request->validated('legal_entity_id') !== null ? (int) $request->validated('legal_entity_id') : null,
            fiscalYearId: $request->validated('fiscal_year_id') !== null ? (int) $request->validated('fiscal_year_id') : null,
            reference: $request->validated('reference'),
            description: $request->validated('description'),
            lines: $lines,
        );
    }

    /**
     * @return array<string, mixed>
     */
    public function attributes(): array
    {
        return [
            'journal_date' => $this->journalDate,
            'branch_id' => $this->branchId,
            'legal_entity_id' => $this->legalEntityId,
            'fiscal_year_id' => $this->fiscalYearId,
            'reference' => $this->reference,
            'description' => $this->description,
        ];
    }

    /**
     * @return list<array<string, mixed>>
     */
    public function lineArrays(): array
    {
        return array_map(fn (JournalEntryLineData $line) => $line->toArray(), $this->lines);
    }
}
