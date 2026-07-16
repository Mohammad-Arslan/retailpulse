<?php

declare(strict_types=1);

namespace App\Http\Requests\Admin\Hr;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

final class UpdateHrEntitySettingsRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'legal_entity_id' => ['required', 'integer', 'exists:organization_entities,id'],
            'default_holiday_calendar_id' => ['nullable', 'integer', 'exists:holiday_calendars,id'],
            'employee_code_sequence_key' => ['nullable', 'string', 'max:64'],
            'settings_json' => ['nullable', 'array'],
            'settings_json.default_leave_fiscal_year_mode' => ['nullable', 'string', 'max:32'],
            'settings_json.require_default_cost_centre' => ['nullable', 'boolean'],
        ];
    }

    protected function prepareForValidation(): void
    {
        $settings = $this->input('settings_json');
        if (is_array($settings) && array_key_exists('require_default_cost_centre', $settings)) {
            $settings['require_default_cost_centre'] = filter_var(
                $settings['require_default_cost_centre'],
                FILTER_VALIDATE_BOOLEAN,
                FILTER_NULL_ON_FAILURE,
            ) ?? false;
            $this->merge(['settings_json' => $settings]);
        }
    }
}
