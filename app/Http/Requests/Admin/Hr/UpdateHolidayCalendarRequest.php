<?php

declare(strict_types=1);

namespace App\Http\Requests\Admin\Hr;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

final class UpdateHolidayCalendarRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->can('holiday.manage') ?? false;
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        /** @var \App\Models\HolidayCalendar $holidayCalendar */
        $holidayCalendar = $this->route('holiday_calendar');

        return [
            'code' => ['sometimes', 'required', 'string', 'max:64', Rule::unique('holiday_calendars', 'code')->ignore($holidayCalendar->id)],
            'name' => ['sometimes', 'required', 'string', 'max:255'],
            'legal_entity_id' => ['nullable', 'integer', 'exists:organization_entities,id'],
            'branch_id' => ['nullable', 'integer', 'exists:branches,id'],
            'status' => ['nullable', Rule::in(['active', 'inactive'])],
        ];
    }
}
