<?php

declare(strict_types=1);

namespace App\DTOs\Payroll;

use App\Http\Requests\Admin\Payroll\UpdateSalaryStructureRequest;

final readonly class UpdateSalaryStructureData
{
    /**
     * @param  array<string, mixed>  $attributes
     * @param  list<array{pay_component_id: int, amount_or_rate: float|null, sequence: int|null}>  $components
     */
    public function __construct(
        public array $attributes,
        public array $components,
    ) {}

    public static function fromRequest(UpdateSalaryStructureRequest $request): self
    {
        $validated = $request->validated();
        $attributes = [];

        foreach (['name', 'status'] as $field) {
            if (array_key_exists($field, $validated)) {
                $attributes[$field] = $validated[$field];
            }
        }

        if (array_key_exists('legal_entity_id', $validated)) {
            $attributes['legal_entity_id'] = $validated['legal_entity_id'] !== null
                ? (int) $validated['legal_entity_id']
                : null;
        }

        return new self(
            attributes: $attributes,
            components: CreateSalaryStructureData::normalizeComponents($validated['components'] ?? []),
        );
    }

    /**
     * @return array<string, mixed>
     */
    public function toArray(): array
    {
        return $this->attributes;
    }
}
