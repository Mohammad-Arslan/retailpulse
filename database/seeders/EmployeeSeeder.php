<?php

declare(strict_types=1);

namespace Database\Seeders;

use App\Models\Branch;
use App\Models\Department;
use App\Models\Designation;
use App\Models\DocumentSequence;
use App\Models\Employee;
use App\Models\EmployeeProfile;
use App\Models\Grade;
use App\Models\OrganizationEntity;
use Illuminate\Database\Seeder;

final class EmployeeSeeder extends Seeder
{
    public function run(): void
    {
        $entity = OrganizationEntity::query()->where('status', 'active')->orderBy('id')->first();
        $branch = Branch::query()->where('is_active', true)->orderBy('id')->first();
        $secondBranch = Branch::query()->where('is_active', true)->orderBy('id')->skip(1)->first() ?? $branch;

        if ($entity === null || $branch === null) {
            return;
        }

        $departments = Department::query()
            ->where('legal_entity_id', $entity->id)
            ->where('status', 'active')
            ->get()
            ->keyBy('code');

        $designations = Designation::query()
            ->where('legal_entity_id', $entity->id)
            ->where('status', 'active')
            ->get()
            ->keyBy('code');

        $grades = Grade::query()
            ->where('legal_entity_id', $entity->id)
            ->where('status', 'active')
            ->get()
            ->keyBy('code');

        $manager = Employee::query()->updateOrCreate(
            ['employee_code' => 'EMP-00001'],
            [
                'legal_entity_id' => $entity->id,
                'primary_branch_id' => $branch->id,
                'department_id' => $departments->get('DEPT-00005')?->id ?? $departments->get('DEPT-00001')?->id,
                'designation_id' => $designations->get('DESIG-00004')?->id,
                'grade_id' => $grades->get('GRADE-00004')?->id,
                'reporting_manager_employee_id' => null,
                'title' => 'Ms',
                'first_name' => 'Amina',
                'middle_name' => null,
                'last_name' => 'Khan',
                'preferred_name' => 'Amina',
                'gender' => 'female',
                'date_of_birth' => '1988-04-12',
                'marital_status' => 'married',
                'nationality' => 'Pakistani',
                'email' => 'amina.khan@retailpulse.local',
                'phone' => '+92-300-1000001',
                'hire_date' => '2020-01-15',
                'probation_end_date' => '2020-04-15',
                'confirmation_date' => '2020-04-15',
                'employment_type' => 'full_time',
                'joined_as' => 'Store Manager',
                'payment_method' => 'bank_transfer',
                'status' => 'active',
            ],
        );

        $this->ensureProfile($manager, [
            'city' => 'Karachi',
            'country' => 'Pakistan',
            'address_line1' => '12 Clifton Block 5',
            'emergency_contact_name' => 'Omar Khan',
            'emergency_contact_phone' => '+92-300-2000001',
            'emergency_contact_relation' => 'Spouse',
            'attendance_grace_minutes' => 10,
            'overtime_eligible' => true,
        ]);

        $staff = [
            [
                'employee_code' => 'EMP-00002',
                'branch_id' => $branch->id,
                'department_code' => 'DEPT-00006',
                'designation_code' => 'DESIG-00001',
                'grade_code' => 'GRADE-00001',
                'title' => 'Mr',
                'first_name' => 'Bilal',
                'last_name' => 'Ahmed',
                'preferred_name' => 'Bilal',
                'gender' => 'male',
                'date_of_birth' => '1995-08-21',
                'marital_status' => 'single',
                'email' => 'bilal.ahmed@retailpulse.local',
                'phone' => '+92-300-1000002',
                'hire_date' => '2023-03-01',
                'probation_end_date' => '2023-06-01',
                'confirmation_date' => '2023-06-01',
                'employment_type' => 'full_time',
                'joined_as' => 'Cashier',
                'city' => 'Karachi',
            ],
            [
                'employee_code' => 'EMP-00003',
                'branch_id' => $branch->id,
                'department_code' => 'DEPT-00006',
                'designation_code' => 'DESIG-00002',
                'grade_code' => 'GRADE-00001',
                'title' => 'Ms',
                'first_name' => 'Sara',
                'last_name' => 'Malik',
                'preferred_name' => 'Sara',
                'gender' => 'female',
                'date_of_birth' => '1997-11-03',
                'marital_status' => 'single',
                'email' => 'sara.malik@retailpulse.local',
                'phone' => '+92-300-1000003',
                'hire_date' => '2024-01-10',
                'probation_end_date' => '2024-04-10',
                'confirmation_date' => null,
                'employment_type' => 'part_time',
                'joined_as' => 'Sales Associate',
                'city' => 'Karachi',
            ],
            [
                'employee_code' => 'EMP-00004',
                'branch_id' => $secondBranch->id,
                'department_code' => 'DEPT-00005',
                'designation_code' => 'DESIG-00003',
                'grade_code' => 'GRADE-00003',
                'title' => 'Mr',
                'first_name' => 'Hassan',
                'last_name' => 'Raza',
                'preferred_name' => 'Hassan',
                'gender' => 'male',
                'date_of_birth' => '1992-02-18',
                'marital_status' => 'married',
                'email' => 'hassan.raza@retailpulse.local',
                'phone' => '+92-300-1000004',
                'hire_date' => '2021-09-01',
                'probation_end_date' => '2021-12-01',
                'confirmation_date' => '2021-12-01',
                'employment_type' => 'full_time',
                'joined_as' => 'Store Supervisor',
                'city' => 'Lahore',
            ],
            [
                'employee_code' => 'EMP-00005',
                'branch_id' => $branch->id,
                'department_code' => 'DEPT-00002',
                'designation_code' => 'DESIG-00005',
                'grade_code' => 'GRADE-00002',
                'title' => 'Ms',
                'first_name' => 'Nadia',
                'last_name' => 'Iqbal',
                'preferred_name' => 'Nadia',
                'gender' => 'female',
                'date_of_birth' => '1990-06-30',
                'marital_status' => 'married',
                'email' => 'nadia.iqbal@retailpulse.local',
                'phone' => '+92-300-1000005',
                'hire_date' => '2019-05-20',
                'probation_end_date' => '2019-08-20',
                'confirmation_date' => '2019-08-20',
                'employment_type' => 'full_time',
                'joined_as' => 'HR Officer',
                'city' => 'Karachi',
                'manager_id' => null,
            ],
            [
                'employee_code' => 'EMP-00006',
                'branch_id' => $branch->id,
                'department_code' => 'DEPT-00003',
                'designation_code' => 'DESIG-00006',
                'grade_code' => 'GRADE-00002',
                'title' => 'Mr',
                'first_name' => 'Imran',
                'last_name' => 'Sheikh',
                'preferred_name' => 'Imran',
                'gender' => 'male',
                'date_of_birth' => '1989-12-09',
                'marital_status' => 'married',
                'email' => 'imran.sheikh@retailpulse.local',
                'phone' => '+92-300-1000006',
                'hire_date' => '2022-07-01',
                'probation_end_date' => '2022-10-01',
                'confirmation_date' => '2022-10-01',
                'employment_type' => 'full_time',
                'joined_as' => 'Accountant',
                'city' => 'Karachi',
                'manager_id' => null,
            ],
        ];

        foreach ($staff as $row) {
            $reportsToManager = ! array_key_exists('manager_id', $row) || $row['manager_id'] !== null;

            $employee = Employee::query()->updateOrCreate(
                ['employee_code' => $row['employee_code']],
                [
                    'legal_entity_id' => $entity->id,
                    'primary_branch_id' => $row['branch_id'],
                    'department_id' => $departments->get($row['department_code'])?->id
                        ?? $departments->get('DEPT-00001')?->id,
                    'designation_id' => $designations->get($row['designation_code'])?->id,
                    'grade_id' => $grades->get($row['grade_code'])?->id,
                    'reporting_manager_employee_id' => $reportsToManager ? $manager->id : null,
                    'title' => $row['title'],
                    'first_name' => $row['first_name'],
                    'last_name' => $row['last_name'],
                    'preferred_name' => $row['preferred_name'],
                    'gender' => $row['gender'],
                    'date_of_birth' => $row['date_of_birth'],
                    'marital_status' => $row['marital_status'],
                    'nationality' => 'Pakistani',
                    'email' => $row['email'],
                    'phone' => $row['phone'],
                    'hire_date' => $row['hire_date'],
                    'probation_end_date' => $row['probation_end_date'],
                    'confirmation_date' => $row['confirmation_date'],
                    'employment_type' => $row['employment_type'],
                    'joined_as' => $row['joined_as'],
                    'payment_method' => 'bank_transfer',
                    'status' => 'active',
                ],
            );

            $this->ensureProfile($employee, [
                'city' => $row['city'],
                'country' => 'Pakistan',
                'attendance_grace_minutes' => 5,
                'overtime_eligible' => true,
            ]);
        }

        $maxCode = 6;
        foreach ([$branch->id, $secondBranch->id] as $branchId) {
            $this->syncEmployeeSequence((int) $branchId, $maxCode + 1);
        }
    }

    /**
     * @param  array<string, mixed>  $attributes
     */
    private function ensureProfile(Employee $employee, array $attributes): void
    {
        EmployeeProfile::query()->updateOrCreate(
            ['employee_id' => $employee->id],
            $attributes,
        );
    }

    private function syncEmployeeSequence(int $branchId, int $nextNumber): void
    {
        DocumentSequence::query()->updateOrCreate(
            [
                'document_type' => 'employee',
                'branch_id' => $branchId,
                'legal_entity_id' => null,
                'fiscal_year_id' => null,
            ],
            [
                'prefix' => 'EMP',
                'next_number' => $nextNumber,
                'status' => 'active',
            ],
        );
    }
}
