<?php

declare(strict_types=1);

namespace App\Http\Requests\Admin\Payroll;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

final class StoreTaxSlabRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->can('payroll.manage-tax-slabs') ?? false;
    }

    protected function prepareForValidation(): void
    {
        foreach (['upper_bound', 'effective_to'] as $field) {
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
            'legal_entity_id' => ['required', 'integer', 'exists:organization_entities,id'],
            'effective_from' => ['required', 'date'],
            'effective_to' => ['nullable', 'date', 'after_or_equal:effective_from'],
            'lower_bound' => ['required', 'numeric', 'min:0'],
            'upper_bound' => ['nullable', 'numeric', 'gte:lower_bound'],
            'fixed_amount' => ['required', 'numeric', 'min:0'],
            'marginal_rate' => ['required', 'numeric', 'min:0'],
            'status' => ['required', Rule::in(['active', 'inactive'])],
        ];
    }
}
