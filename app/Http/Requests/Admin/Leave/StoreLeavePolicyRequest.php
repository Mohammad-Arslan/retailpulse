<?php

declare(strict_types=1);

namespace App\Http\Requests\Admin\Leave;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

final class StoreLeavePolicyRequest extends FormRequest
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

        if (! $this->has('proration_on_join')) {
            $this->merge(['proration_on_join' => false]);
        }

        if (! $this->has('exclude_public_holidays')) {
            $this->merge(['exclude_public_holidays' => false]);
        }
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'leave_type_id' => ['required', 'integer', 'exists:leave_types,id'],
            'legal_entity_id' => ['nullable', 'integer', 'exists:organization_entities,id'],
            'accrual_method' => ['required', 'string', Rule::in(['fixed_annual', 'monthly_accrual', 'per_worked_hours'])],
            'accrual_rate' => ['required', 'numeric', 'min:0'],
            'max_balance' => ['nullable', 'numeric', 'min:0'],
            'carry_forward_limit' => ['nullable', 'numeric', 'min:0'],
            'carry_forward_expiry_months' => ['nullable', 'integer', 'min:0', 'max:120'],
            'proration_on_join' => ['boolean'],
            'exclude_public_holidays' => ['boolean'],
            'effective_from' => ['required', 'date'],
            'effective_to' => ['nullable', 'date', 'after_or_equal:effective_from'],
            'status' => ['required', Rule::in(['active', 'inactive'])],
        ];
    }
}
