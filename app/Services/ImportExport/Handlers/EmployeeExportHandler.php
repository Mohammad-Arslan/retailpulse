<?php

declare(strict_types=1);

namespace App\Services\ImportExport\Handlers;

use App\Models\Employee;
use App\Models\User;
use App\Services\Accounting\DocumentNumberService;
use App\Services\BranchContextService;
use App\Services\Hr\ReportingHierarchyService;
use App\Services\ImportExport\Contracts\ExportHandler;
use App\Services\ImportExport\ExportContext;
use App\Support\BranchScope;
use Illuminate\Database\Eloquent\Builder;

final class EmployeeExportHandler implements ExportHandler
{
    public function __construct(
        private readonly BranchContextService $branchContext,
    ) {}

    public function columns(): array
    {
        return (new EmployeeImportHandler(
            app(DocumentNumberService::class),
            app(ReportingHierarchyService::class),
            app(BranchContextService::class),
        ))->columns();
    }

    public function query(ExportContext $context): Builder
    {
        $query = Employee::query()
            ->with([
                'legalEntity:id,legal_name,tax_registration_no',
                'primaryBranch:id,code,name',
                'department:id,code',
                'designation:id,code',
                'grade:id,code',
                'reportingManager:id,employee_code',
                'defaultCostCentre:id,code',
                'salaryStructure:id,code',
            ])
            ->orderBy('employee_code');

        $owner = User::query()->find($context->userId);
        BranchScope::applyAccessible(
            $query,
            $owner !== null ? $this->branchContext->accessibleBranchIds($owner) : [],
            'primary_branch_id',
        );

        $filters = $context->options['filters'] ?? [];

        if (! empty($filters['search'])) {
            $search = (string) $filters['search'];
            $query->where(function (Builder $builder) use ($search): void {
                $builder->where('employee_code', 'like', "%{$search}%")
                    ->orWhere('first_name', 'like', "%{$search}%")
                    ->orWhere('last_name', 'like', "%{$search}%")
                    ->orWhere('email', 'like', "%{$search}%")
                    ->orWhere('phone', 'like', "%{$search}%");
            });
        }

        if (! empty($filters['status'])) {
            $query->where('status', (string) $filters['status']);
        }

        if (! empty($filters['branch_id'])) {
            $query->where('primary_branch_id', (int) $filters['branch_id']);
        }

        if (! empty($filters['department_id'])) {
            $query->where('department_id', (int) $filters['department_id']);
        }

        return $query;
    }

    public function map(mixed $record, ExportContext $context): array
    {
        /** @var Employee $record */
        return [
            'employee_code' => $record->employee_code,
            'first_name' => $record->first_name,
            'last_name' => $record->last_name,
            'middle_name' => $record->middle_name ?? '',
            'preferred_name' => $record->preferred_name ?? '',
            'title' => $record->title ?? '',
            'email' => $record->email ?? '',
            'phone' => $record->phone ?? '',
            'gender' => $record->gender ?? '',
            'date_of_birth' => optional($record->date_of_birth)?->toDateString() ?? '',
            'marital_status' => $record->marital_status ?? '',
            'nationality' => $record->nationality ?? '',
            'national_id' => '',
            'hire_date' => optional($record->hire_date)?->toDateString() ?? '',
            'probation_end_date' => optional($record->probation_end_date)?->toDateString() ?? '',
            'confirmation_date' => optional($record->confirmation_date)?->toDateString() ?? '',
            'contract_end_date' => optional($record->contract_end_date)?->toDateString() ?? '',
            'termination_date' => optional($record->termination_date)?->toDateString() ?? '',
            'employment_type' => $record->employment_type ?? '',
            'joined_as' => $record->joined_as ?? '',
            'payment_method' => $record->payment_method ?? '',
            'status' => $record->status ?? '',
            'legal_entity' => $record->legalEntity?->legal_name ?? '',
            'primary_branch_code' => $record->primaryBranch?->code ?? '',
            'department_code' => $record->department?->code ?? '',
            'designation_code' => $record->designation?->code ?? '',
            'grade_code' => $record->grade?->code ?? '',
            'manager_employee_code' => $record->reportingManager?->employee_code ?? '',
            'cost_centre_code' => $record->defaultCostCentre?->code ?? '',
            'salary_structure_code' => $record->salaryStructure?->code ?? '',
        ];
    }

    public function chunkSize(): int
    {
        return 500;
    }
}
