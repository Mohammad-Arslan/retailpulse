<?php

declare(strict_types=1);

namespace App\Support;

use App\Models\Employee;
use App\Models\EmployeeBankAccount;
use App\Models\EmployeeBranchAssignment;
use App\Models\EmployeeDependent;
use App\Models\HolidayCalendarAssignment;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;

final class EmployeePresenter
{
    /**
     * @return array<string, mixed>
     */
    public static function listItem(Employee $employee): array
    {
        return [
            'id' => $employee->id,
            'employee_code' => $employee->employee_code,
            'name' => $employee->fullName(),
            'email' => $employee->email,
            'employment_type' => $employee->employment_type,
            'status' => $employee->status,
            'hire_date' => $employee->hire_date?->toDateString(),
            'branch' => $employee->primaryBranch?->name,
            'legal_entity' => $employee->legalEntity?->legal_name,
            'department' => $employee->department?->name,
            'designation' => $employee->designation?->name,
        ];
    }

    public static function paginated(LengthAwarePaginator $paginator): LengthAwarePaginator
    {
        return $paginator->through(fn (Employee $employee) => self::listItem($employee));
    }

    /**
     * @return array<string, mixed>
     */
    public static function detail(Employee $employee): array
    {
        $profile = $employee->profile;
        $medical = $employee->medicalProfile;
        $shift = $employee->shiftPreference;

        return [
            'id' => $employee->id,
            'employee_code' => $employee->employee_code,
            'title' => $employee->title,
            'first_name' => $employee->first_name,
            'middle_name' => $employee->middle_name,
            'last_name' => $employee->last_name,
            'preferred_name' => $employee->preferred_name,
            'name' => $employee->fullName(),
            'gender' => $employee->gender,
            'date_of_birth' => $employee->date_of_birth?->toDateString(),
            'marital_status' => $employee->marital_status,
            'nationality' => $employee->nationality,
            'national_id' => $employee->national_id_encrypted,
            'email' => $employee->email,
            'phone' => $employee->phone,
            'user_id' => $employee->user_id,
            'legal_entity_id' => $employee->legal_entity_id,
            'primary_branch_id' => $employee->primary_branch_id,
            'department_id' => $employee->department_id,
            'designation_id' => $employee->designation_id,
            'grade_id' => $employee->grade_id,
            'reporting_manager_employee_id' => $employee->reporting_manager_employee_id,
            'salary_structure_id' => $employee->salary_structure_id,
            'hire_date' => $employee->hire_date?->toDateString(),
            'termination_date' => $employee->termination_date?->toDateString(),
            'probation_end_date' => $employee->probation_end_date?->toDateString(),
            'confirmation_date' => $employee->confirmation_date?->toDateString(),
            'contract_end_date' => $employee->contract_end_date?->toDateString(),
            'employment_type' => $employee->employment_type,
            'joined_as' => $employee->joined_as,
            'default_cost_centre_id' => $employee->default_cost_centre_id,
            'payment_method' => $employee->payment_method,
            'status' => $employee->status,
            'legal_entity' => $employee->legalEntity?->legal_name,
            'branch' => $employee->primaryBranch?->name,
            'cost_centre' => $employee->defaultCostCentre?->name,
            'department' => $employee->department?->name,
            'designation' => $employee->designation?->name,
            'grade' => $employee->grade?->name,
            'reporting_manager' => $employee->reportingManager?->fullName(),
            'user_name' => $employee->user?->name,
            'salary_structure' => $employee->salaryStructure?->name,
            'profile' => [
                'address_line1' => $profile?->address_line1,
                'address_line2' => $profile?->address_line2,
                'city' => $profile?->city,
                'state' => $profile?->state,
                'postal_code' => $profile?->postal_code,
                'country' => $profile?->country,
                'emergency_contact_name' => $profile?->emergency_contact_name,
                'emergency_contact_phone' => $profile?->emergency_contact_phone,
                'emergency_contact_relation' => $profile?->emergency_contact_relation,
                'attendance_grace_minutes' => $profile?->attendance_grace_minutes ?? 0,
                'overtime_eligible' => $profile?->overtime_eligible ?? true,
            ],
            'medical' => [
                'blood_group' => $medical?->blood_group,
                'allergies' => $medical?->allergies,
                'conditions' => $medical?->conditions,
                'insurance_provider' => $medical?->insurance_provider,
                'insurance_policy_no' => $medical?->insurance_policy_no,
                'emergency_notes' => $medical?->emergency_notes,
            ],
            'shift' => [
                'shift_label' => $shift?->shift_label,
                'start_time' => $shift?->start_time ? substr((string) $shift->start_time, 0, 5) : '',
                'end_time' => $shift?->end_time ? substr((string) $shift->end_time, 0, 5) : '',
                'rest_days' => $shift?->rest_days ?? [],
                'weekend_days_enabled' => (bool) ($shift?->weekend_days_enabled ?? false),
                'weekend_days' => $shift?->weekend_days ?? [],
                'notes' => $shift?->notes,
            ],
            'dependents' => $employee->dependents->map(fn (EmployeeDependent $d) => [
                'id' => $d->id,
                'name' => $d->name,
                'relation' => $d->relation,
                'date_of_birth' => $d->date_of_birth?->toDateString(),
                'gender' => $d->gender,
                'national_id' => $d->national_id,
                'phone' => $d->phone,
                'is_emergency_contact' => $d->is_emergency_contact,
            ])->values()->all(),
            'bank_accounts' => $employee->bankAccounts->map(fn (EmployeeBankAccount $b) => [
                'id' => $b->id,
                'label' => $b->label,
                'bank_name' => $b->bank_name,
                'account_number' => $b->account_number,
                'iban' => $b->iban,
                'currency_code' => $b->currency_code,
                'payment_method' => $b->payment_method,
                'is_primary' => $b->is_primary,
            ])->values()->all(),
            'branch_assignments' => $employee->branchAssignments->map(fn (EmployeeBranchAssignment $a) => [
                'id' => $a->id,
                'branch_id' => $a->branch_id,
                'branch_name' => $a->branch?->name,
                'effective_from' => $a->effective_from->toDateString(),
                'effective_to' => $a->effective_to?->toDateString(),
                'status' => $a->status,
            ])->values()->all(),
            'images' => ImagePresenter::collection(
                $employee->relationLoaded('images') ? $employee->images : collect(),
            ),
            'holiday_assignments' => ($employee->relationLoaded('holidayAssignments')
                ? $employee->getRelation('holidayAssignments')
                : collect()
            )->map(fn (HolidayCalendarAssignment $a) => [
                'id' => $a->id,
                'calendar_id' => $a->holiday_calendar_id,
                'calendar_name' => $a->calendar?->name,
                'effective_from' => $a->effective_from->toDateString(),
                'effective_to' => $a->effective_to?->toDateString(),
                'status' => $a->status,
            ])->values()->all(),
        ];
    }
}
