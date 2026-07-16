<?php

declare(strict_types=1);

namespace App\Http\Requests\Admin\Hr;

use App\Models\Employee;
use Illuminate\Contracts\Validation\Validator;

final class UpdateEmployeeRequest extends NormalizesNullableEmployeeForeignKeys
{
    use ValidatesEmployeeProfilePayload;

    public function authorize(): bool
    {
        return $this->user()?->can('hr.manage-employees') ?? false;
    }

    protected function prepareForValidation(): void
    {
        $this->normalizeNullableIntegers([
            'department_id',
            'designation_id',
            'grade_id',
            'reporting_manager_employee_id',
            'default_cost_centre_id',
            'user_id',
            'salary_structure_id',
            'holiday_calendar_id',
        ]);

        foreach (['date_of_birth', 'termination_date', 'probation_end_date', 'confirmation_date', 'contract_end_date', 'national_id'] as $field) {
            if ($this->input($field) === '') {
                $this->merge([$field => null]);
            }
        }

        $this->normalizeEmployeeProfileBooleans();
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        /** @var Employee|null $employee */
        $employee = $this->route('employee');

        $rules = $this->employeeCoreRules($employee?->id);
        $currentStatus = $employee?->status;

        // Termination/reactivation go through dedicated actions (EmployeeController::terminate/reactivate).
        // The general update form may only toggle active <-> inactive, or leave status unchanged (no-op).
        $rules['status'] = [
            'required',
            function (string $attribute, mixed $value, \Closure $fail) use ($currentStatus): void {
                if ($value === $currentStatus) {
                    return;
                }

                if (! in_array($value, ['active', 'inactive'], true)) {
                    $fail(__('Use The Terminate Or Reactivate Action To Change This Status.'));
                }
            },
        ];

        return $rules;
    }

    public function withValidator(Validator $validator): void
    {
        $this->withEmployeeProfileValidator($validator);
    }
}
