<?php

declare(strict_types=1);

namespace App\Http\Requests\Admin\Attendance;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

final class StoreManualAttendanceRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->can('attendance.record') ?? false;
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'employee_id' => ['required', 'integer', 'exists:employees,id'],
            'branch_id' => ['required', 'integer', 'exists:branches,id'],
            'action' => ['required', 'string', Rule::in(['clock_in', 'clock_out'])],
            'clocked_at' => ['nullable', 'date'],
            'open_record_id' => ['nullable', 'integer', 'exists:attendance_records,id'],
        ];
    }
}
