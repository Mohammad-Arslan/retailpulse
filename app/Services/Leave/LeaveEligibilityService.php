<?php

declare(strict_types=1);

namespace App\Services\Leave;

use App\Models\Employee;
use App\Models\LeavePolicy;
use Carbon\CarbonImmutable;

final class LeaveEligibilityService
{
    /**
     * Evaluates $policy->eligibility_json against $employee. An absent/null
     * policy, or a policy with no eligibility_json (or an empty one), matches
     * everyone — identical to the pre-eligibility behavior. $asOf is the
     * evaluation moment, not the employee's hire date: min_tenure_months must
     * be measured against "now", since tenure evaluated at hire date is
     * always zero and would permanently fail any nonzero requirement.
     */
    public function isEligible(Employee $employee, LeavePolicy $policy, CarbonImmutable $asOf): bool
    {
        $rules = $policy->eligibility_json;

        if (! is_array($rules) || $rules === []) {
            return true;
        }

        return $this->matchesGenders($employee, $rules)
            && $this->matchesGradeIds($employee, $rules)
            && $this->matchesEmploymentTypes($employee, $rules)
            && $this->matchesMinTenureMonths($employee, $rules, $asOf);
    }

    /**
     * @param  array<string, mixed>  $rules
     */
    private function matchesGenders(Employee $employee, array $rules): bool
    {
        $genders = $rules['genders'] ?? null;

        if (! is_array($genders) || $genders === []) {
            return true;
        }

        return in_array($employee->gender, $genders, true);
    }

    /**
     * @param  array<string, mixed>  $rules
     */
    private function matchesGradeIds(Employee $employee, array $rules): bool
    {
        $gradeIds = $rules['grade_ids'] ?? null;

        if (! is_array($gradeIds) || $gradeIds === []) {
            return true;
        }

        return $employee->grade_id !== null && in_array((int) $employee->grade_id, array_map('intval', $gradeIds), true);
    }

    /**
     * @param  array<string, mixed>  $rules
     */
    private function matchesEmploymentTypes(Employee $employee, array $rules): bool
    {
        $employmentTypes = $rules['employment_types'] ?? null;

        if (! is_array($employmentTypes) || $employmentTypes === []) {
            return true;
        }

        return $employee->employment_type !== null && in_array($employee->employment_type, $employmentTypes, true);
    }

    /**
     * @param  array<string, mixed>  $rules
     */
    private function matchesMinTenureMonths(Employee $employee, array $rules, CarbonImmutable $asOf): bool
    {
        $minTenureMonths = $rules['min_tenure_months'] ?? null;

        if ($minTenureMonths === null) {
            return true;
        }

        if ($employee->hire_date === null) {
            return false;
        }

        $hireDate = CarbonImmutable::parse($employee->hire_date);

        return $hireDate->diffInMonths($asOf) >= (int) $minTenureMonths;
    }
}
