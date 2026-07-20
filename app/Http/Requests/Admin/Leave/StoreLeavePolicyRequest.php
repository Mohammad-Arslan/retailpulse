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
        foreach ([
            'legal_entity_id',
            'max_balance',
            'carry_forward_limit',
            'carry_forward_expiry_months',
            'short_leave_max_hours',
            'short_leave_max_requests_per_month',
            'encashment_max_days',
            'effective_to',
        ] as $field) {
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

        if (! $this->has('exclude_weekends')) {
            $this->merge(['exclude_weekends' => false]);
        }

        if (! $this->has('out_station_deducts_balance')) {
            $this->merge(['out_station_deducts_balance' => false]);
        }

        if (! $this->has('encashment_allowed')) {
            $this->merge(['encashment_allowed' => false]);
        }

        if (! $this->has('encashment_requires_approval')) {
            $this->merge(['encashment_requires_approval' => true]);
        }

        if (! $this->has('year_end_excess_disposition') || $this->input('year_end_excess_disposition') === '') {
            $this->merge(['year_end_excess_disposition' => 'expire']);
        }

        if (! $this->has('negative_leave_balance_policy') || $this->input('negative_leave_balance_policy') === '') {
            $this->merge(['negative_leave_balance_policy' => 'block']);
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
            'negative_leave_balance_policy' => ['required', 'string', Rule::in(['block', 'warn', 'allow'])],
            'eligibility_json' => ['nullable', 'array'],
            'eligibility_json.genders' => ['nullable', 'array'],
            'eligibility_json.genders.*' => ['string', 'max:32'],
            'eligibility_json.grade_ids' => ['nullable', 'array'],
            'eligibility_json.grade_ids.*' => ['integer', 'exists:grades,id'],
            'eligibility_json.employment_types' => ['nullable', 'array'],
            'eligibility_json.employment_types.*' => ['string', 'exists:hr_employment_types,code'],
            'eligibility_json.min_tenure_months' => ['nullable', 'integer', 'min:0'],
            'proration_on_join' => ['boolean'],
            'exclude_public_holidays' => ['boolean'],
            'exclude_weekends' => ['boolean'],
            'short_leave_max_hours' => ['nullable', 'numeric', 'min:0.25', 'max:24'],
            'short_leave_max_requests_per_month' => ['nullable', 'integer', 'min:1', 'max:31'],
            'out_station_deducts_balance' => ['boolean'],
            'encashment_allowed' => ['boolean'],
            'encashment_max_days' => ['nullable', 'numeric', 'min:0.25'],
            'encashment_requires_approval' => ['boolean'],
            'year_end_excess_disposition' => ['required', 'string', Rule::in(['expire', 'encash'])],
            'effective_from' => ['required', 'date'],
            'effective_to' => ['nullable', 'date', 'after_or_equal:effective_from'],
            'status' => ['required', Rule::in(['active', 'inactive'])],
        ];
    }
}
