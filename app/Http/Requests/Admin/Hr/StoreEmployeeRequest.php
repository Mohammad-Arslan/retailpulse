<?php

declare(strict_types=1);

namespace App\Http\Requests\Admin\Hr;

use Illuminate\Contracts\Validation\Validator;

final class StoreEmployeeRequest extends NormalizesNullableEmployeeForeignKeys
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
        return $this->employeeCoreRules();
    }

    public function withValidator(Validator $validator): void
    {
        $this->withEmployeeProfileValidator($validator);
    }
}
