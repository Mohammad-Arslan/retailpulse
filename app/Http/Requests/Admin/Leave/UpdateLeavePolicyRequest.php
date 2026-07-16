<?php

declare(strict_types=1);

namespace App\Http\Requests\Admin\Leave;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

final class UpdateLeavePolicyRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    protected function prepareForValidation(): void
    {
        foreach (['legal_entity_id', 'max_balance', 'carry_forward_limit', 'carry_forward_expiry_months', 'effective_to'] as $field) {
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
            'leave_type_id' => ['sometimes', 'required', 'integer', 'exists:leave_types,id'],
            'legal_entity_id' => ['sometimes', 'nullable', 'integer', 'exists:organization_entities,id'],
            'accrual_method' => ['sometimes', 'required', 'string', Rule::in(['fixed_annual', 'monthly_accrual', 'per_worked_hours'])],
            'accrual_rate' => ['sometimes', 'required', 'numeric', 'min:0'],
            'max_balance' => ['sometimes', 'nullable', 'numeric', 'min:0'],
            'carry_forward_limit' => ['sometimes', 'nullable', 'numeric', 'min:0'],
            'carry_forward_expiry_months' => ['sometimes', 'nullable', 'integer', 'min:0', 'max:120'],
            'proration_on_join' => ['sometimes', 'boolean'],
            'exclude_public_holidays' => ['sometimes', 'boolean'],
            'effective_from' => ['sometimes', 'required', 'date'],
            'effective_to' => ['sometimes', 'nullable', 'date', 'after_or_equal:effective_from'],
            'status' => ['sometimes', 'required', Rule::in(['active', 'inactive'])],
        ];
    }
}
