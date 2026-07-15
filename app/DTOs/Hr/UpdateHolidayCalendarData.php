<?php

declare(strict_types=1);

namespace App\DTOs\Hr;

use App\Http\Requests\Admin\Hr\UpdateHolidayCalendarRequest;

final readonly class UpdateHolidayCalendarData
{
    /**
     * @param  array<string, mixed>  $attributes
     */
    public function __construct(
        public array $attributes,
    ) {}

    public static function fromRequest(UpdateHolidayCalendarRequest $request): self
    {
        $validated = $request->validated();
        $attributes = [];

        foreach (['code', 'name', 'status'] as $field) {
            if (array_key_exists($field, $validated)) {
                $attributes[$field] = $validated[$field];
            }
        }

        if (array_key_exists('legal_entity_id', $validated)) {
            $attributes['legal_entity_id'] = $validated['legal_entity_id'] !== null
                ? (int) $validated['legal_entity_id']
                : null;
        }

        if (array_key_exists('branch_id', $validated)) {
            $attributes['branch_id'] = $validated['branch_id'] !== null
                ? (int) $validated['branch_id']
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
