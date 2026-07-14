<?php

declare(strict_types=1);

namespace App\Http\Requests\Admin\Payroll;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

final class StorePayComponentRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->can('payroll.manage-components') ?? false;
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'code' => ['required', 'string', 'max:64', 'unique:pay_components,code'],
            'name' => ['required', 'string', 'max:255'],
            'type' => ['required', Rule::in(['earning', 'deduction', 'employer_contribution', 'statutory', 'reimbursement'])],
            'calculation_type' => [
                'required',
                Rule::in(['fixed', 'percentage_of', 'table_lookup', 'formula']),
                function (string $attribute, mixed $value, \Closure $fail): void {
                    if ($value === 'formula') {
                        $fail(__('Formula Components Are Not Supported Yet'));
                    }
                },
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
