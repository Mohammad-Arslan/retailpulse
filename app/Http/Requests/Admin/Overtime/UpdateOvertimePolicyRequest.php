<?php

declare(strict_types=1);

namespace App\Http\Requests\Admin\Overtime;

use App\Services\Overtime\OvertimeEngine;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

final class UpdateOvertimePolicyRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->can('overtime.manage-policies') ?? false;
    }

    protected function prepareForValidation(): void
    {
        foreach (['legal_entity_id', 'branch_id', 'weekly_threshold_minutes', 'toil_expiry_months', 'effective_to'] as $field) {
            if ($this->input($field) === '') {
                $this->merge([$field => null]);
            }
        }

        if (! $this->has('rest_day_applies')) {
            $this->merge(['rest_day_applies' => false]);
        }

        if (! $this->has('public_holiday_applies')) {
            $this->merge(['public_holiday_applies' => false]);
        }
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'legal_entity_id' => ['nullable', 'integer', 'exists:organization_entities,id'],
            'branch_id' => ['nullable', 'integer', 'exists:branches,id'],
            'daily_threshold_minutes' => ['required', 'integer', 'min:0'],
            'weekly_threshold_minutes' => ['nullable', 'integer', 'min:0'],
            'rest_day_applies' => ['boolean'],
            'public_holiday_applies' => ['boolean'],
            'toil_expiry_months' => ['nullable', 'integer', 'min:1', 'max:120'],
            'effective_from' => ['required', 'date'],
            'effective_to' => ['nullable', 'date', 'after_or_equal:effective_from'],
            'status' => ['required', Rule::in(['active', 'inactive'])],
            'priority' => ['required', 'integer', 'min:0', 'max:65535'],
            'multipliers' => ['required', 'array', 'min:1'],
            'multipliers.*.day_type' => [
                'required',
                'string',
                Rule::in([
                    OvertimeEngine::DAY_TYPE_WEEKDAY,
                    OvertimeEngine::DAY_TYPE_WEEKEND,
                    OvertimeEngine::DAY_TYPE_REST_DAY,
                    OvertimeEngine::DAY_TYPE_PUBLIC_HOLIDAY,
                ]),
                'distinct',
            ],
            'multipliers.*.multiplier' => ['required', 'numeric', 'min:0'],
            'multipliers.*.compensation_type' => ['required', 'string', Rule::in(['cash', 'toil', 'employee_choice'])],
        ];
    }
}
