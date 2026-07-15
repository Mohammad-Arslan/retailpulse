<?php

declare(strict_types=1);

namespace App\Http\Requests\Admin\Hr;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

final class StoreDesignationRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->can('hr.manage-org') ?? false;
    }

    protected function prepareForValidation(): void
    {
        foreach (['legal_entity_id', 'default_grade_id'] as $field) {
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
            'legal_entity_id' => ['nullable', 'integer', 'exists:organization_entities,id'],
            'name' => ['required', 'string', 'max:255'],
            'default_grade_id' => ['nullable', 'integer', 'exists:grades,id'],
            'status' => ['nullable', Rule::in(['active', 'inactive'])],
        ];
    }
}
