<?php

declare(strict_types=1);

namespace App\Http\Requests\Admin;

use App\Enums\CountScheduleFrequency;
use App\Enums\CountScopeType;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Validator;

final class UpdateCountScheduleRuleRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->can('inventory.cycle-count') ?? false;
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'scope_type' => ['required', Rule::in(CountScopeType::values())],
            'scope_id' => ['nullable', 'integer'],
            'frequency' => ['required', Rule::in(CountScheduleFrequency::values())],
            'day_of_week' => ['nullable', 'integer', 'min:0', 'max:6'],
            'day_of_month' => ['nullable', 'integer', 'min:1', 'max:31'],
            'blind_count' => ['sometimes', 'boolean'],
            'freeze_mode' => ['sometimes', 'boolean'],
            'is_active' => ['sometimes', 'boolean'],
        ];
    }

    public function withValidator(Validator $validator): void
    {
        $validator->after(function (Validator $validator): void {
            $scopeType = $this->input('scope_type');
            $scopeId = $this->input('scope_id');

            if ($scopeType !== CountScopeType::Full->value && ($scopeId === null || $scopeId === '')) {
                $validator->errors()->add('scope_id', __('Scope ID is required for zone or category counts.'));
            }

            $frequency = $this->input('frequency');

            if ($frequency === CountScheduleFrequency::Weekly->value && $this->input('day_of_week') === null) {
                $validator->errors()->add('day_of_week', __('Day of week is required for weekly schedules.'));
            }

            if ($frequency === CountScheduleFrequency::Monthly->value && $this->input('day_of_month') === null) {
                $validator->errors()->add('day_of_month', __('Day of month is required for monthly schedules.'));
            }
        });
    }
}
