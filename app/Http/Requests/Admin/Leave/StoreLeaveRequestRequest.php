<?php

declare(strict_types=1);

namespace App\Http\Requests\Admin\Leave;

use App\Models\Employee;
use App\Services\BranchContextService;
use App\Support\BranchScope;
use Illuminate\Contracts\Validation\Validator;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

final class StoreLeaveRequestRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->can('leave.request') ?? false;
    }

    protected function prepareForValidation(): void
    {
        if (! $this->has('duration_type') || $this->input('duration_type') === '') {
            $this->merge(['duration_type' => 'full_day']);
        }
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'employee_id' => ['required', 'integer', 'exists:employees,id'],
            'leave_type_id' => ['required', 'integer', 'exists:leave_types,id'],
            'start_date' => ['required', 'date'],
            'end_date' => ['required', 'date', 'after_or_equal:start_date'],
            'duration_type' => ['required', 'string', Rule::in(['full_day', 'half_day', 'short_leave', 'out_station'])],
            'session' => ['required_if:duration_type,half_day', 'nullable', 'string', Rule::in(['morning', 'afternoon'])],
            'start_time' => ['required_if:duration_type,short_leave', 'nullable', 'date_format:H:i'],
            'end_time' => ['required_if:duration_type,short_leave', 'nullable', 'date_format:H:i', 'after:start_time'],
            'reason' => ['nullable', 'string', 'max:2000'],
        ];
    }

    public function withValidator(Validator $validator): void
    {
        $validator->after(function (Validator $validator): void {
            $employeeId = $this->input('employee_id');
            if ($employeeId !== null) {
                $accessibleBranchIds = app(BranchContextService::class)->accessibleBranchIds($this->user());
                $employeeBranchId = Employee::query()->whereKey((int) $employeeId)->value('primary_branch_id');
                if ($employeeBranchId !== null && ! BranchScope::canAccess((int) $employeeBranchId, $accessibleBranchIds)) {
                    $validator->errors()->add('employee_id', __('You Do Not Have Access To This Employee.'));
                }
            }

            $durationType = $this->input('duration_type');
            $startDate = $this->input('start_date');
            $endDate = $this->input('end_date');

            if (
                in_array($durationType, ['half_day', 'short_leave'], true)
                && $startDate !== null
                && $endDate !== null
                && $startDate !== $endDate
            ) {
                $validator->errors()->add(
                    'end_date',
                    __('Half day and short leave requests must be for a single date.'),
                );
            }
        });
    }
}
