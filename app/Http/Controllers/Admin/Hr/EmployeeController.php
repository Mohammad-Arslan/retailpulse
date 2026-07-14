<?php

declare(strict_types=1);

namespace App\Http\Controllers\Admin\Hr;

use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\Hr\StoreEmployeeRequest;
use App\Http\Requests\Admin\Hr\UpdateEmployeeRequest;
use App\Models\Branch;
use App\Models\CostCentre;
use App\Models\Employee;
use App\Models\OrganizationEntity;
use App\Services\Hr\EmployeeService;
use App\Support\ListPagination;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

final class EmployeeController extends Controller
{
    public function __construct(
        private readonly EmployeeService $employees,
    ) {}

    public function index(Request $request): Response
    {
        $this->authorize('viewAny', Employee::class);

        $filters = ListPagination::filters($request, ['search', 'status', 'branch_id', 'sort', 'direction']);
        $paginator = $this->employees->paginate($filters, ListPagination::resolve($filters['per_page']));

        return Inertia::render('Admin/Hr/Employees/Index', [
            'employees' => $paginator->through(fn (Employee $e) => [
                'id' => $e->id,
                'employee_code' => $e->employee_code,
                'name' => $e->fullName(),
                'email' => $e->email,
                'employment_type' => $e->employment_type,
                'status' => $e->status,
                'hire_date' => $e->hire_date?->toDateString(),
                'branch' => $e->primaryBranch?->name,
                'legal_entity' => $e->legalEntity?->legal_name,
            ]),
            'filters' => $filters,
            'branches' => Branch::query()->where('is_active', true)->orderBy('name')->get(['id', 'name', 'code']),
        ]);
    }

    public function create(): Response
    {
        $this->authorize('create', Employee::class);

        return Inertia::render('Admin/Hr/Employees/Create', $this->formOptions());
    }

    public function store(StoreEmployeeRequest $request): RedirectResponse
    {
        $this->authorize('create', Employee::class);

        $employee = $this->employees->create($request->validated());

        return redirect()
            ->route('admin.hr.employees.show', $employee)
            ->with('success', __('Employee Created Successfully.'));
    }

    public function show(Employee $employee): Response
    {
        $this->authorize('view', $employee);

        $employee->load(['legalEntity', 'primaryBranch', 'defaultCostCentre', 'user']);

        return Inertia::render('Admin/Hr/Employees/Show', [
            'employee' => $this->present($employee),
        ]);
    }

    public function edit(Employee $employee): Response
    {
        $this->authorize('update', $employee);

        $employee->load(['legalEntity', 'primaryBranch', 'defaultCostCentre', 'user']);

        return Inertia::render('Admin/Hr/Employees/Edit', [
            'employee' => $this->present($employee),
            ...$this->formOptions(),
        ]);
    }

    public function update(UpdateEmployeeRequest $request, Employee $employee): RedirectResponse
    {
        $this->authorize('update', $employee);

        $this->employees->update($employee, $request->validated());

        return redirect()
            ->route('admin.hr.employees.show', $employee)
            ->with('success', __('Employee Updated Successfully.'));
    }

    /**
     * @return array<string, mixed>
     */
    private function formOptions(): array
    {
        return [
            'legalEntities' => OrganizationEntity::query()
                ->where('status', 'active')
                ->orderBy('legal_name')
                ->get(['id', 'legal_name', 'functional_currency_code']),
            'branches' => Branch::query()->where('is_active', true)->orderBy('name')->get(['id', 'name', 'code']),
            'costCentres' => CostCentre::query()->where('status', 'active')->orderBy('name')->get(['id', 'code', 'name']),
            'employmentTypes' => ['full_time', 'part_time', 'contract', 'hourly'],
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function present(Employee $employee): array
    {
        return [
            'id' => $employee->id,
            'employee_code' => $employee->employee_code,
            'first_name' => $employee->first_name,
            'last_name' => $employee->last_name,
            'name' => $employee->fullName(),
            'email' => $employee->email,
            'phone' => $employee->phone,
            'user_id' => $employee->user_id,
            'legal_entity_id' => $employee->legal_entity_id,
            'primary_branch_id' => $employee->primary_branch_id,
            'hire_date' => $employee->hire_date?->toDateString(),
            'termination_date' => $employee->termination_date?->toDateString(),
            'employment_type' => $employee->employment_type,
            'default_cost_centre_id' => $employee->default_cost_centre_id,
            'payment_method' => $employee->payment_method,
            'bank_details_encrypted' => $employee->bank_details_encrypted,
            'status' => $employee->status,
            'legal_entity' => $employee->legalEntity?->legal_name,
            'branch' => $employee->primaryBranch?->name,
            'cost_centre' => $employee->defaultCostCentre?->name,
            'user_name' => $employee->user?->name,
        ];
    }
}
