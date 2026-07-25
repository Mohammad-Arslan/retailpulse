<?php

declare(strict_types=1);

namespace App\DTOs\Payroll;

use App\Http\Requests\Admin\Payroll\StoreSalaryStructureRequest;

final readonly class CreateSalaryStructureData
{
    /**
     * @param  list<array{pay_component_id: int, amount_or_rate: float|null, sequence: int|null}>  $components
     */
    public function __construct(
        public ?int $legalEntityId,
        public string $name,
        public string $status,
        public array $components,
    ) {}

    public static function fromRequest(StoreSalaryStructureRequest $request): self
    {
        $validated = $request->validated();

        return new self(
            legalEntityId: isset($validated['legal_entity_id']) ? (int) $validated['legal_entity_id'] : null,
            name: (string) $validated['name'],
            status: (string) ($validated['status'] ?? 'active'),
            components: self::normalizeComponents($validated['components'] ?? []),
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
            'status' => $this->status,
        ];
    }

    /**
     * @param  array<int, array<string, mixed>>  $components
     * @return list<array{pay_component_id: int, amount_or_rate: float|null, sequence: int|null}>
     */
    public static function normalizeComponents(array $components): array
    {
        return array_map(static fn (array $c) => [
            'pay_component_id' => (int) $c['pay_component_id'],
            'amount_or_rate' => isset($c['amount_or_rate']) ? (float) $c['amount_or_rate'] : null,
            'sequence' => isset($c['sequence']) ? (int) $c['sequence'] : null,
        ], $components);
    }
}
