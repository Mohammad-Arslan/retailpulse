<?php

declare(strict_types=1);

namespace App\Http\Requests\Admin\Attendance;

use App\Models\Employee;
use App\Services\BranchContextService;
use App\Support\BranchScope;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Validator;

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

    public function withValidator(Validator $validator): void
    {
        $validator->after(function (Validator $validator): void {
            $accessibleBranchIds = app(BranchContextService::class)->accessibleBranchIds($this->user());

            $branchId = $this->input('branch_id');
            if ($branchId !== null && ! BranchScope::canAccess((int) $branchId, $accessibleBranchIds)) {
                $validator->errors()->add('branch_id', __('You Do Not Have Access To This Branch.'));
            }

            $employeeId = $this->input('employee_id');
            if ($employeeId !== null && $accessibleBranchIds !== null) {
                $employeeBranchId = Employee::query()->whereKey((int) $employeeId)->value('primary_branch_id');
                if ($employeeBranchId !== null && ! BranchScope::canAccess((int) $employeeBranchId, $accessibleBranchIds)) {
                    $validator->errors()->add('employee_id', __('You Do Not Have Access To This Employee.'));
                }
            }
        });
    }
}
