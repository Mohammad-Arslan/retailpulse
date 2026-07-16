<?php

declare(strict_types=1);

namespace App\Http\Requests\Admin\Hr;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

final class UpdateDepartmentRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->can('hr.manage-org') ?? false;
    }

    protected function prepareForValidation(): void
    {
        foreach (['parent_id', 'head_employee_id', 'cost_centre_id'] as $field) {
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
            'legal_entity_id' => ['sometimes', 'required', 'integer', 'exists:organization_entities,id'],
            'name' => ['sometimes', 'required', 'string', 'max:255'],
            'parent_id' => ['nullable', 'integer', 'exists:departments,id'],
            'head_employee_id' => ['nullable', 'integer', 'exists:employees,id'],
            'cost_centre_id' => ['nullable', 'integer', 'exists:cost_centres,id'],
            'status' => ['nullable', Rule::in(['active', 'inactive'])],
        ];
    }
}
