<?php

declare(strict_types=1);

namespace App\Http\Requests\Admin\Payroll;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

final class StoreStatutorySchemeRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->can('payroll.manage-statutory') ?? false;
    }

    protected function prepareForValidation(): void
    {
        foreach ([
            'wage_ceiling',
            'account_mapping_key_employee',
            'account_mapping_key_employer',
            'effective_to',
        ] as $field) {
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
            'code' => ['required', 'string', 'max:64', 'unique:statutory_schemes,code'],
            'name' => ['required', 'string', 'max:255'],
            'legal_entity_id' => ['required', 'integer', 'exists:organization_entities,id'],
            'calculation_type' => ['required', 'string', 'max:32', Rule::in(['percentage_of_wage'])],
            'employee_rate' => ['required', 'numeric', 'min:0'],
            'employer_rate' => ['required', 'numeric', 'min:0'],
            'wage_ceiling' => ['nullable', 'numeric', 'min:0'],
            'account_mapping_key_employee' => ['nullable', 'string', 'max:128'],
            'account_mapping_key_employer' => ['nullable', 'string', 'max:128'],
            'effective_from' => ['required', 'date'],
            'effective_to' => ['nullable', 'date', 'after_or_equal:effective_from'],
            'status' => ['required', Rule::in(['active', 'inactive'])],
        ];
    }
}
