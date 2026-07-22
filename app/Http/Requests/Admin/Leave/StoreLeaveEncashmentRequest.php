<?php

declare(strict_types=1);

namespace App\Http\Requests\Admin\Leave;

use App\Models\Employee;
use App\Services\BranchContextService;
use App\Support\BranchScope;
use Illuminate\Contracts\Validation\Validator;
use Illuminate\Foundation\Http\FormRequest;

final class StoreLeaveEncashmentRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->can('leave.request-encashment') ?? false;
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'employee_id' => ['required', 'integer', 'exists:employees,id'],
            'leave_type_id' => ['required', 'integer', 'exists:leave_types,id'],
            'days' => ['required', 'numeric', 'min:0.25'],
            'reason' => ['nullable', 'string', 'max:2000'],
        ];
    }

    public function withValidator(Validator $validator): void
    {
        $validator->after(function (Validator $validator): void {
            $employeeId = $this->input('employee_id');
            if ($employeeId === null) {
                return;
            }

            $accessibleBranchIds = app(BranchContextService::class)->accessibleBranchIds($this->user());
            $employeeBranchId = Employee::query()->whereKey((int) $employeeId)->value('primary_branch_id');
            if ($employeeBranchId !== null && ! BranchScope::canAccess((int) $employeeBranchId, $accessibleBranchIds)) {
                $validator->errors()->add('employee_id', __('You Do Not Have Access To This Employee.'));
            }
        });
    }
}
