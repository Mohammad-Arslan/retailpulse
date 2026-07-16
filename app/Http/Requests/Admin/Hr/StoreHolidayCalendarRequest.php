<?php

declare(strict_types=1);

namespace App\Http\Requests\Admin\Hr;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

final class StoreHolidayCalendarRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->can('holiday.manage') ?? false;
    }

    protected function prepareForValidation(): void
    {
        foreach (['legal_entity_id', 'branch_id'] as $field) {
            if ($this->input($field) === '') {
                $this->merge([$field => null]);
            }
        }
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'name' => ['required', 'string', 'max:255'],
            'legal_entity_id' => ['nullable', 'integer', 'exists:organization_entities,id'],
            'branch_id' => ['nullable', 'integer', 'exists:branches,id'],
            'status' => ['nullable', Rule::in(['active', 'inactive'])],
        ];
    }
}
