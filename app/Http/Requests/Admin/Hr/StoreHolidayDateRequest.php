<?php

declare(strict_types=1);

namespace App\Http\Requests\Admin\Hr;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

final class StoreHolidayDateRequest extends FormRequest
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
            'holiday_date' => [
                'required',
                'date',
                Rule::unique('holiday_dates', 'holiday_date')->where('holiday_calendar_id', $holidayCalendar->id),
            ],
            'name' => ['required', 'string', 'max:255'],
            'holiday_type' => ['nullable', Rule::in(['public', 'optional', 'company'])],
            'is_paid' => ['nullable', 'boolean'],
            'is_recurring' => ['nullable', 'boolean'],
            'recurrence_month' => ['nullable', 'integer', 'min:1', 'max:12'],
            'recurrence_day' => ['nullable', 'integer', 'min:1', 'max:31'],
        ];
    }
}
