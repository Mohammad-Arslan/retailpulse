<?php

declare(strict_types=1);

namespace App\DTOs\Hr;

use App\Http\Requests\Admin\Hr\UpdateGradeRequest;

final readonly class UpdateGradeData
{
    /**
     * @param  array<string, mixed>  $attributes
     */
    public function __construct(
        public array $attributes,
    ) {}

    public static function fromRequest(UpdateGradeRequest $request): self
    {
        $validated = $request->validated();
        $attributes = [];

        foreach ([
            'name', 'currency_code', 'min_amount', 'mid_amount', 'max_amount',
            'effective_from', 'effective_to', 'status',
        ] as $field) {
            if (array_key_exists($field, $validated)) {
                $attributes[$field] = $validated[$field];
            }
        }

        if (array_key_exists('legal_entity_id', $validated)) {
            $attributes['legal_entity_id'] = $validated['legal_entity_id'] !== null
                ? (int) $validated['legal_entity_id']
                : null;
        }

        if (array_key_exists('rank', $validated)) {
            $attributes['rank'] = (int) ($validated['rank'] ?? 0);
        }

        if (array_key_exists('enforce_salary_band', $validated)) {
            $attributes['enforce_salary_band'] = (bool) $validated['enforce_salary_band'];
        }

        return new self($attributes);
    }

    /**
     * @return array<string, mixed>
     */
    public function toArray(): array
    {
        return $this->attributes;
    }
}
