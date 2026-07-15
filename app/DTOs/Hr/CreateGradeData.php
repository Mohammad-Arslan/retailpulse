<?php

declare(strict_types=1);

namespace App\DTOs\Hr;

use App\Http\Requests\Admin\Hr\StoreGradeRequest;

final readonly class CreateGradeData
{
    public function __construct(
        public ?int $legalEntityId,
        public string $name,
        public int $rank,
        public ?string $currencyCode,
        public ?string $minAmount,
        public ?string $midAmount,
        public ?string $maxAmount,
        public bool $enforceSalaryBand,
        public ?string $effectiveFrom,
        public ?string $effectiveTo,
        public string $status,
    ) {}

    public static function fromRequest(StoreGradeRequest $request): self
    {
        $validated = $request->validated();

        return new self(
            legalEntityId: isset($validated['legal_entity_id']) ? (int) $validated['legal_entity_id'] : null,
            name: (string) $validated['name'],
            rank: (int) ($validated['rank'] ?? 0),
            currencyCode: $validated['currency_code'] ?? null,
            minAmount: isset($validated['min_amount']) ? (string) $validated['min_amount'] : null,
            midAmount: isset($validated['mid_amount']) ? (string) $validated['mid_amount'] : null,
            maxAmount: isset($validated['max_amount']) ? (string) $validated['max_amount'] : null,
            enforceSalaryBand: (bool) ($validated['enforce_salary_band'] ?? false),
            effectiveFrom: $validated['effective_from'] ?? null,
            effectiveTo: $validated['effective_to'] ?? null,
            status: (string) ($validated['status'] ?? 'active'),
        );
    }

    /**
     * @return array<string, mixed>
     */
    public function toArray(): array
    {
        return [
            'legal_entity_id' => $this->legalEntityId,
            'name' => $this->name,
            'rank' => $this->rank,
            'currency_code' => $this->currencyCode,
            'min_amount' => $this->minAmount,
            'mid_amount' => $this->midAmount,
            'max_amount' => $this->maxAmount,
            'enforce_salary_band' => $this->enforceSalaryBand,
            'effective_from' => $this->effectiveFrom,
            'effective_to' => $this->effectiveTo,
            'status' => $this->status,
        ];
    }
}
