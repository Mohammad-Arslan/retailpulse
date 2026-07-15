<?php

declare(strict_types=1);

namespace App\Http\Requests\Admin\Hr;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

final class StoreHolidayCalendarRequest extends FormRequest
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
        return [
            'code' => ['required', 'string', 'max:64', 'unique:holiday_calendars,code'],
            'name' => ['required', 'string', 'max:255'],
            'legal_entity_id' => ['nullable', 'integer', 'exists:organization_entities,id'],
            'branch_id' => ['nullable', 'integer', 'exists:branches,id'],
            'status' => ['nullable', Rule::in(['active', 'inactive'])],
        ];
    }
}
