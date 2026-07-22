<?php

declare(strict_types=1);

namespace App\Services\ImportExport\Handlers;

use App\Models\Branch;
use App\Models\CostCentre;
use App\Models\Department;
use App\Models\Designation;
use App\Models\Employee;
use App\Models\Grade;
use App\Models\OrganizationEntity;
use App\Models\SalaryStructure;
use App\Models\User;
use App\Services\Accounting\DocumentNumberService;
use App\Services\BranchContextService;
use App\Services\Hr\ReportingHierarchyService;
use App\Services\ImportExport\Contracts\ImportHandler;
use App\Services\ImportExport\ImportContext;
use App\Services\ImportExport\ImportRowResult;
use App\Support\BranchScope;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

final class EmployeeImportHandler implements ImportHandler
{
    public function __construct(
        private readonly DocumentNumberService $documentNumbers,
        private readonly ReportingHierarchyService $hierarchy,
        private readonly BranchContextService $branchContext,
    ) {}

    public function columns(): array
    {
        return [
            [
                'key' => 'employee_code',
                'label' => 'Employee Code',
                'required' => false,
                'default_rules' => [['rule' => 'nullable'], ['rule' => 'string', 'min' => 1, 'max' => 64]],
                'default_transforms' => ['trim', 'uppercase', 'nullify_empty'],
            ],
            [
                'key' => 'first_name',
                'label' => 'First Name',
                'required' => true,
                'default_rules' => [['rule' => 'required'], ['rule' => 'string', 'min' => 1, 'max' => 120]],
                'default_transforms' => ['trim'],
            ],
            [
                'key' => 'last_name',
                'label' => 'Last Name',
                'required' => true,
                'default_rules' => [['rule' => 'required'], ['rule' => 'string', 'min' => 1, 'max' => 120]],
                'default_transforms' => ['trim'],
            ],
            [
                'key' => 'middle_name',
                'label' => 'Middle Name',
                'required' => false,
                'default_rules' => [['rule' => 'nullable'], ['rule' => 'string', 'max' => 120]],
                'default_transforms' => ['trim', 'nullify_empty'],
            ],
            [
                'key' => 'preferred_name',
                'label' => 'Preferred Name',
                'required' => false,
                'default_rules' => [['rule' => 'nullable'], ['rule' => 'string', 'max' => 120]],
                'default_transforms' => ['trim', 'nullify_empty'],
            ],
            [
                'key' => 'title',
                'label' => 'Title',
                'required' => false,
                'default_rules' => [['rule' => 'nullable'], ['rule' => 'string', 'max' => 32]],
                'default_transforms' => ['trim', 'nullify_empty'],
            ],
            [
                'key' => 'email',
                'label' => 'Email',
                'required' => false,
                'default_rules' => [['rule' => 'nullable'], ['rule' => 'email'], ['rule' => 'string', 'max' => 255]],
                'default_transforms' => ['trim', 'nullify_empty'],
            ],
            [
                'key' => 'phone',
                'label' => 'Phone',
                'required' => false,
                'default_rules' => [['rule' => 'nullable'], ['rule' => 'string', 'max' => 32]],
                'default_transforms' => ['trim', 'nullify_empty'],
            ],
            [
                'key' => 'gender',
                'label' => 'Gender',
                'required' => false,
                'default_rules' => [
                    ['rule' => 'nullable'],
                    ['rule' => 'in_list', 'values' => ['male', 'female', 'other', 'undisclosed']],
                ],
                'default_transforms' => ['trim', 'lowercase', 'nullify_empty'],
            ],
            [
                'key' => 'date_of_birth',
                'label' => 'Date Of Birth',
                'required' => false,
                'default_rules' => [['rule' => 'nullable'], ['rule' => 'date']],
                'default_transforms' => ['trim', 'date_normalize', 'nullify_empty'],
            ],
            [
                'key' => 'marital_status',
                'label' => 'Marital Status',
                'required' => false,
                'default_rules' => [
                    ['rule' => 'nullable'],
                    ['rule' => 'in_list', 'values' => ['single', 'married', 'divorced', 'widowed', 'other']],
                ],
                'default_transforms' => ['trim', 'lowercase', 'nullify_empty'],
            ],
            [
                'key' => 'nationality',
                'label' => 'Nationality',
                'required' => false,
                'default_rules' => [['rule' => 'nullable'], ['rule' => 'string', 'max' => 120]],
                'default_transforms' => ['trim', 'nullify_empty'],
            ],
            [
                'key' => 'national_id',
                'label' => 'National ID',
                'required' => false,
                'default_rules' => [['rule' => 'nullable'], ['rule' => 'string', 'max' => 64]],
                'default_transforms' => ['trim', 'nullify_empty'],
            ],
            [
                'key' => 'hire_date',
                'label' => 'Hire Date',
                'required' => true,
                'default_rules' => [['rule' => 'required'], ['rule' => 'date']],
                'default_transforms' => ['trim', 'date_normalize'],
            ],
            [
                'key' => 'probation_end_date',
                'label' => 'Probation End Date',
                'required' => false,
                'default_rules' => [['rule' => 'nullable'], ['rule' => 'date']],
                'default_transforms' => ['trim', 'date_normalize', 'nullify_empty'],
            ],
            [
                'key' => 'confirmation_date',
                'label' => 'Confirmation Date',
                'required' => false,
                'default_rules' => [['rule' => 'nullable'], ['rule' => 'date']],
                'default_transforms' => ['trim', 'date_normalize', 'nullify_empty'],
            ],
            [
                'key' => 'contract_end_date',
                'label' => 'Contract End Date',
                'required' => false,
                'default_rules' => [['rule' => 'nullable'], ['rule' => 'date']],
                'default_transforms' => ['trim', 'date_normalize', 'nullify_empty'],
            ],
            [
                'key' => 'termination_date',
                'label' => 'Termination Date',
                'required' => false,
                'default_rules' => [['rule' => 'nullable'], ['rule' => 'date']],
                'default_transforms' => ['trim', 'date_normalize', 'nullify_empty'],
            ],
            [
                'key' => 'employment_type',
                'label' => 'Employment Type',
                'required' => false,
                'default_rules' => [
                    ['rule' => 'nullable'],
                    ['rule' => 'in_list', 'values' => ['full_time', 'part_time', 'contract', 'hourly']],
                ],
                'default_transforms' => ['trim', 'lowercase', 'nullify_empty'],
            ],
            [
                'key' => 'joined_as',
                'label' => 'Joined As',
                'required' => false,
                'default_rules' => [['rule' => 'nullable'], ['rule' => 'string', 'max' => 120]],
                'default_transforms' => ['trim', 'nullify_empty'],
            ],
            [
                'key' => 'payment_method',
                'label' => 'Payment Method',
                'required' => false,
                'default_rules' => [['rule' => 'nullable'], ['rule' => 'string', 'max' => 32]],
                'default_transforms' => ['trim', 'nullify_empty'],
            ],
            [
                'key' => 'status',
                'label' => 'Status',
                'required' => false,
                'default_rules' => [
                    ['rule' => 'nullable'],
                    ['rule' => 'in_list', 'values' => ['active', 'inactive', 'terminated']],
                ],
                'default_transforms' => ['trim', 'lowercase', 'nullify_empty'],
            ],
            [
                'key' => 'legal_entity',
                'label' => 'Legal Entity',
                'required' => true,
                'default_rules' => [['rule' => 'required'], ['rule' => 'string', 'min' => 1, 'max' => 255]],
                'default_transforms' => ['trim'],
            ],
            [
                'key' => 'primary_branch_code',
                'label' => 'Primary Branch Code',
                'required' => true,
                'default_rules' => [
                    ['rule' => 'required'],
                    ['rule' => 'string', 'max' => 64],
                    ['rule' => 'exists_in_db', 'table' => 'branches', 'column' => 'code'],
                ],
                'default_transforms' => ['trim'],
            ],
            [
                'key' => 'department_code',
                'label' => 'Department Code',
                'required' => false,
                'default_rules' => [
                    ['rule' => 'nullable'],
                    ['rule' => 'string', 'max' => 64],
                    ['rule' => 'exists_in_db', 'table' => 'departments', 'column' => 'code'],
                ],
                'default_transforms' => ['trim', 'nullify_empty'],
            ],
            [
                'key' => 'designation_code',
                'label' => 'Designation Code',
                'required' => false,
                'default_rules' => [
                    ['rule' => 'nullable'],
                    ['rule' => 'string', 'max' => 64],
                    ['rule' => 'exists_in_db', 'table' => 'designations', 'column' => 'code'],
                ],
                'default_transforms' => ['trim', 'nullify_empty'],
            ],
            [
                'key' => 'grade_code',
                'label' => 'Grade Code',
                'required' => false,
                'default_rules' => [
                    ['rule' => 'nullable'],
                    ['rule' => 'string', 'max' => 64],
                    ['rule' => 'exists_in_db', 'table' => 'grades', 'column' => 'code'],
                ],
                'default_transforms' => ['trim', 'nullify_empty'],
            ],
            [
                'key' => 'manager_employee_code',
                'label' => 'Manager Employee Code',
                'required' => false,
                'default_rules' => [
                    ['rule' => 'nullable'],
                    ['rule' => 'string', 'max' => 64],
                    ['rule' => 'exists_in_db', 'table' => 'employees', 'column' => 'employee_code'],
                ],
                'default_transforms' => ['trim', 'uppercase', 'nullify_empty'],
            ],
            [
                'key' => 'cost_centre_code',
                'label' => 'Cost Centre Code',
                'required' => false,
                'default_rules' => [
                    ['rule' => 'nullable'],
                    ['rule' => 'string', 'max' => 64],
                    ['rule' => 'exists_in_db', 'table' => 'cost_centres', 'column' => 'code'],
                ],
                'default_transforms' => ['trim', 'nullify_empty'],
            ],
            [
                'key' => 'salary_structure_code',
                'label' => 'Salary Structure Code',
                'required' => false,
                'default_rules' => [
                    ['rule' => 'nullable'],
                    ['rule' => 'string', 'max' => 64],
                    ['rule' => 'exists_in_db', 'table' => 'salary_structures', 'column' => 'code'],
                ],
                'default_transforms' => ['trim', 'nullify_empty'],
            ],
        ];
    }

    public function validateRow(array $row, ImportContext $context): array
    {
        $errors = [];
        $code = isset($row['employee_code']) ? (string) $row['employee_code'] : '';
        $exists = $code !== '' && Employee::query()->where('employee_code', $code)->exists();

        if ($context->mode === 'create' && $exists) {
            $errors['employee_code'] = ['Employee code already exists.'];
        }

        if (in_array($context->mode, ['update', 'upsert'], true) && $code === '') {
            $errors['employee_code'] = ['Employee code is required for update or upsert.'];
        }

        if ($context->mode === 'update' && $code !== '' && ! $exists) {
            $errors['employee_code'] = ['Employee not found for update.'];
        }

        $legalEntity = (string) ($row['legal_entity'] ?? '');
        if ($legalEntity !== '' && ! $this->findLegalEntityId($legalEntity)) {
            $errors['legal_entity'] = ['Legal entity not found.'];
        }

        $branchCode = (string) ($row['primary_branch_code'] ?? '');
        if ($branchCode !== '' && ! $this->canImportToBranchCode($branchCode, $context)) {
            $errors['primary_branch_code'] = ['You do not have access to this branch.'];
        }

        $managerCode = isset($row['manager_employee_code']) ? (string) $row['manager_employee_code'] : '';
        if ($managerCode !== '' && $code !== '' && strtoupper($managerCode) === strtoupper($code)) {
            $errors['manager_employee_code'] = ['An employee cannot report to themselves.'];
        }

        return $errors;
    }

    public function processRow(array $row, ImportContext $context): ImportRowResult
    {
        if ($context->isDryRun) {
            return ImportRowResult::success(null);
        }

        try {
            return DB::transaction(function () use ($row, $context) {
                $resolved = $this->resolveForeignKeys($row);
                if (isset($resolved['error'])) {
                    return ImportRowResult::failure($resolved['error']);
                }

                $code = isset($row['employee_code']) ? (string) $row['employee_code'] : '';
                $employee = $code !== ''
                    ? Employee::query()->where('employee_code', $code)->first()
                    : null;

                if ($employee === null && $context->mode === 'update') {
                    return ImportRowResult::failure('Employee not found for update.');
                }

                $attributes = [
                    'first_name' => (string) ($row['first_name'] ?? ''),
                    'last_name' => (string) ($row['last_name'] ?? ''),
                    'middle_name' => $row['middle_name'] ?? null,
                    'preferred_name' => $row['preferred_name'] ?? null,
                    'title' => $row['title'] ?? null,
                    'email' => $row['email'] ?? null,
                    'phone' => $row['phone'] ?? null,
                    'gender' => $row['gender'] ?? null,
                    'date_of_birth' => $row['date_of_birth'] ?? null,
                    'marital_status' => $row['marital_status'] ?? null,
                    'nationality' => $row['nationality'] ?? null,
                    'hire_date' => $row['hire_date'] ?? null,
                    'probation_end_date' => $row['probation_end_date'] ?? null,
                    'confirmation_date' => $row['confirmation_date'] ?? null,
                    'contract_end_date' => $row['contract_end_date'] ?? null,
                    'termination_date' => $row['termination_date'] ?? null,
                    'employment_type' => $row['employment_type'] ?? 'full_time',
                    'joined_as' => $row['joined_as'] ?? null,
                    'payment_method' => $row['payment_method'] ?? null,
                    'status' => $row['status'] ?? 'active',
                    'legal_entity_id' => $resolved['legal_entity_id'],
                    'primary_branch_id' => $resolved['primary_branch_id'],
                    'department_id' => $resolved['department_id'],
                    'designation_id' => $resolved['designation_id'],
                    'grade_id' => $resolved['grade_id'],
                    'reporting_manager_employee_id' => $resolved['reporting_manager_employee_id'],
                    'default_cost_centre_id' => $resolved['default_cost_centre_id'],
                    'salary_structure_id' => $resolved['salary_structure_id'],
                ];

                if (array_key_exists('national_id', $row)) {
                    $attributes['national_id_encrypted'] = $row['national_id'];
                }

                if ($employee !== null) {
                    $oldManagerId = $employee->reporting_manager_employee_id;
                    $newManagerId = $attributes['reporting_manager_employee_id'];

                    if ($newManagerId !== null) {
                        $this->hierarchy->assertNoCycle($employee->id, (int) $newManagerId);
                    }

                    $employee->update($attributes);

                    if ($newManagerId !== $oldManagerId) {
                        $this->hierarchy->recordManagerChange($employee, $newManagerId, $context->userId);
                    }

                    return ImportRowResult::success($employee->id);
                }

                if ($context->mode === 'update') {
                    return ImportRowResult::failure('Employee not found for update.');
                }

                $employeeCode = $code !== ''
                    ? $code
                    : $this->documentNumbers->next('employee', 'EMP', $resolved['primary_branch_id']);

                $employee = Employee::query()->create([
                    ...$attributes,
                    'employee_code' => $employeeCode,
                ]);

                if ($employee->reporting_manager_employee_id !== null) {
                    $this->hierarchy->assertNoCycle($employee->id, (int) $employee->reporting_manager_employee_id);
                    $this->hierarchy->recordManagerChange(
                        $employee,
                        (int) $employee->reporting_manager_employee_id,
                        $context->userId,
                    );
                }

                return ImportRowResult::success($employee->id);
            });
        } catch (ValidationException $exception) {
            $message = collect($exception->errors())->flatten()->first() ?? $exception->getMessage();

            return ImportRowResult::failure((string) $message);
        }
    }

    public function afterImport(ImportContext $context): void
    {
        //
    }

    public function chunkSize(): int
    {
        return 100;
    }

    private function canImportToBranchCode(string $branchCode, ImportContext $context): bool
    {
        $branchId = Branch::query()->where('code', $branchCode)->value('id');
        if ($branchId === null) {
            return true;
        }

        $owner = User::query()->find($context->userId);
        $accessibleBranchIds = $owner !== null ? $this->branchContext->accessibleBranchIds($owner) : [];

        return BranchScope::canAccess((int) $branchId, $accessibleBranchIds);
    }

    private function findLegalEntityId(string $value): ?int
    {
        $entity = OrganizationEntity::query()
            ->where(function ($query) use ($value): void {
                $query->where('legal_name', $value)
                    ->orWhere('tax_registration_no', $value);
            })
            ->first(['id']);

        return $entity?->id;
    }

    /**
     * @param  array<string, mixed>  $row
     * @return array{error?: string, legal_entity_id?: int, primary_branch_id?: int, department_id?: ?int, designation_id?: ?int, grade_id?: ?int, reporting_manager_employee_id?: ?int, default_cost_centre_id?: ?int, salary_structure_id?: ?int}
     */
    private function resolveForeignKeys(array $row): array
    {
        $legalEntityId = $this->findLegalEntityId((string) ($row['legal_entity'] ?? ''));
        if ($legalEntityId === null) {
            return ['error' => 'Legal entity not found.'];
        }

        $branch = Branch::query()->where('code', (string) ($row['primary_branch_code'] ?? ''))->first(['id']);
        if ($branch === null) {
            return ['error' => 'Primary branch not found.'];
        }

        $departmentId = null;
        if (! empty($row['department_code'])) {
            $departmentId = Department::query()->where('code', (string) $row['department_code'])->value('id');
            if ($departmentId === null) {
                return ['error' => 'Department not found.'];
            }
        }

        $designationId = null;
        if (! empty($row['designation_code'])) {
            $designationId = Designation::query()->where('code', (string) $row['designation_code'])->value('id');
            if ($designationId === null) {
                return ['error' => 'Designation not found.'];
            }
        }

        $gradeId = null;
        if (! empty($row['grade_code'])) {
            $gradeId = Grade::query()->where('code', (string) $row['grade_code'])->value('id');
            if ($gradeId === null) {
                return ['error' => 'Grade not found.'];
            }
        }

        $managerId = null;
        if (! empty($row['manager_employee_code'])) {
            $managerId = Employee::query()
                ->where('employee_code', (string) $row['manager_employee_code'])
                ->value('id');
            if ($managerId === null) {
                return ['error' => 'Manager employee not found.'];
            }
        }

        $costCentreId = null;
        if (! empty($row['cost_centre_code'])) {
            $costCentreId = CostCentre::query()->where('code', (string) $row['cost_centre_code'])->value('id');
            if ($costCentreId === null) {
                return ['error' => 'Cost centre not found.'];
            }
        }

        $salaryStructureId = null;
        if (! empty($row['salary_structure_code'])) {
            $salaryStructureId = SalaryStructure::query()
                ->where('code', (string) $row['salary_structure_code'])
                ->value('id');
            if ($salaryStructureId === null) {
                return ['error' => 'Salary structure not found.'];
            }
        }

        return [
            'legal_entity_id' => $legalEntityId,
            'primary_branch_id' => (int) $branch->id,
            'department_id' => $departmentId !== null ? (int) $departmentId : null,
            'designation_id' => $designationId !== null ? (int) $designationId : null,
            'grade_id' => $gradeId !== null ? (int) $gradeId : null,
            'reporting_manager_employee_id' => $managerId !== null ? (int) $managerId : null,
            'default_cost_centre_id' => $costCentreId !== null ? (int) $costCentreId : null,
            'salary_structure_id' => $salaryStructureId !== null ? (int) $salaryStructureId : null,
        ];
    }
}
