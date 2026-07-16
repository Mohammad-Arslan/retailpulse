<?php

declare(strict_types=1);

namespace App\Http\Requests\Admin\Payroll;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

final class UpdatePayComponentRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->can('payroll.manage-components') ?? false;
    }

    protected function prepareForValidation(): void
    {
        foreach (['basis_component_id', 'legal_entity_id', 'effective_to', 'rate', 'account_mapping_key', 'formula_expression'] as $field) {
            if ($this->input($field) === '') {
                $this->merge([$field => null]);
            }
        }

        if (! $this->has('taxable')) {
            $this->merge(['taxable' => false]);
        }
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        $componentId = $this->route('pay_component')?->id ?? $this->route('pay_component');

        return [
            'code' => ['required', 'string', 'max:64', Rule::unique('pay_components', 'code')->ignore($componentId)],
            'name' => ['required', 'string', 'max:255'],
            'type' => ['required', Rule::in(['earning', 'deduction', 'employer_contribution', 'statutory', 'reimbursement'])],
            'calculation_type' => [
                'required',
                Rule::in(['fixed', 'percentage_of', 'table_lookup']),
            ],
            'basis_component_id' => ['nullable', 'integer', 'exists:pay_components,id'],
            'rate' => ['nullable', 'numeric', 'min:0'],
            'formula_expression' => ['nullable', 'string'],
            'taxable' => ['boolean'],
            'account_mapping_key' => ['nullable', 'string', 'max:128'],
            'effective_from' => ['required', 'date'],
            'effective_to' => ['nullable', 'date', 'after_or_equal:effective_from'],
            'legal_entity_id' => ['nullable', 'integer', 'exists:organization_entities,id'],
            'status' => ['required', Rule::in(['active', 'inactive'])],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'calculation_type.in' => __('Formula Components Are Not Supported Yet'),
        ];
    }
}
