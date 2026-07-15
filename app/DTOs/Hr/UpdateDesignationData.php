<?php

declare(strict_types=1);

namespace App\DTOs\Hr;

use App\Http\Requests\Admin\Hr\UpdateDesignationRequest;

final readonly class UpdateDesignationData
{
    /**
     * @param  array<string, mixed>  $attributes
     */
    public function __construct(
        public array $attributes,
    ) {}

    public static function fromRequest(UpdateDesignationRequest $request): self
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

        if (array_key_exists('default_grade_id', $validated)) {
            $attributes['default_grade_id'] = $validated['default_grade_id'] !== null
                ? (int) $validated['default_grade_id']
                : null;
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
