<?php

declare(strict_types=1);

namespace App\Http\Requests\Admin\Overtime;

use App\Models\Employee;
use App\Services\BranchContextService;
use App\Support\BranchScope;
use Illuminate\Contracts\Validation\Validator;
use Illuminate\Foundation\Http\FormRequest;

final class StoreToilCashClaimRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->can('toil.request-cash-claim') ?? false;
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'employee_id' => ['required', 'integer', 'exists:employees,id'],
            'hours' => ['required', 'numeric', 'min:0.25'],
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
