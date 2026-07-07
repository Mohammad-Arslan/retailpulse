<?php

declare(strict_types=1);

namespace App\DTOs\Accounting;

use App\Enums\FiscalYearStatus;
use App\Http\Requests\Admin\Accounting\StoreFiscalYearRequest;

final readonly class CreateFiscalYearData
{
    public function __construct(
        public string $name,
        public ?int $legalEntityId,
        public string $startDate,
        public string $endDate,
        public FiscalYearStatus $status,
    ) {}

    public static function fromRequest(StoreFiscalYearRequest $request): self
    {
        $status = $request->validated('status');

        return new self(
            name: $request->validated('name'),
            legalEntityId: $request->validated('legal_entity_id') !== null
                ? (int) $request->validated('legal_entity_id') : null,
            startDate: $request->validated('start_date'),
            endDate: $request->validated('end_date'),
            status: $status !== null ? FiscalYearStatus::from($status) : FiscalYearStatus::Open,
        );
    }

    /**
     * @return array<string, mixed>
     */
    public function toArray(): array
    {
        return [
            'name' => $this->name,
            'legal_entity_id' => $this->legalEntityId,
            'start_date' => $this->startDate,
            'end_date' => $this->endDate,
            'status' => $this->status->value,
        ];
    }
}
