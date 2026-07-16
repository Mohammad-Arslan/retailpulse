<?php

declare(strict_types=1);

namespace App\Http\Controllers\Admin\Hr;

use App\DTOs\Hr\CreateEmployeeData;
use App\DTOs\Hr\TerminateEmployeeData;
use App\DTOs\Hr\UpdateEmployeeData;
use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\Hr\StoreEmployeeRequest;
use App\Http\Requests\Admin\Hr\TerminateEmployeeRequest;
use App\Http\Requests\Admin\Hr\UpdateEmployeeRequest;
use App\Models\Employee;
use App\Models\Image;
use App\Services\Hr\EmployeeService;
use App\Services\ImageService;
use App\Support\ListPagination;
use DomainException;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

final class EmployeeController extends Controller
{
    public function __construct(
        private readonly EmployeeService $employees,
        private readonly ImageService $images,
    ) {}

    public function index(Request $request): Response
    {
        $this->authorize('viewAny', Employee::class);

        $filters = ListPagination::filters($request, ['search', 'status', 'branch_id', 'department_id', 'sort', 'direction']);

        return Inertia::render(
            'Admin/Hr/Employees/Index',
            $this->employees->indexPayload($filters, ListPagination::resolve($filters['per_page'])),
        );
    }

    public function create(): Response
    {
        $this->authorize('create', Employee::class);

        return Inertia::render('Admin/Hr/Employees/Create', $this->employees->createPayload());
    }

    public function store(StoreEmployeeRequest $request): RedirectResponse
    {
        $this->authorize('create', Employee::class);

        $employee = $this->employees->create(CreateEmployeeData::fromRequest($request));

        return redirect()
            ->route('admin.hr.employees.edit', $employee)
            ->with('success', __('Employee Created Successfully. Complete Remaining Profile Tabs.'));
    }

    public function show(Employee $employee): Response
    {
        $this->authorize('view', $employee);

        return Inertia::render('Admin/Hr/Employees/Show', [
            ...$this->employees->showPayload($employee),
            'tab' => request()->string('tab')->toString() ?: 'basic',
        ]);
    }

    public function edit(Request $request, Employee $employee): Response
    {
        $this->authorize('update', $employee);

        return Inertia::render('Admin/Hr/Employees/Edit', [
            ...$this->employees->editPayload($employee),
            'tab' => $request->string('tab')->toString() ?: 'basic',
        ]);
    }

    public function update(UpdateEmployeeRequest $request, Employee $employee): RedirectResponse
    {
        $this->authorize('update', $employee);

        $this->employees->update($employee, UpdateEmployeeData::fromRequest($request));

        $tab = $request->string('active_tab')->toString() ?: 'basic';

        return redirect()
            ->route('admin.hr.employees.edit', ['employee' => $employee, 'tab' => $tab])
            ->with('success', __('Employee Updated Successfully.'));
    }

    public function terminate(TerminateEmployeeRequest $request, Employee $employee): RedirectResponse
    {
        $this->authorize('terminate', $employee);

        try {
            $this->employees->terminate($employee, TerminateEmployeeData::fromRequest($request));
        } catch (DomainException $e) {
            return back()->with('error', $e->getMessage());
        }

        return back()->with('success', __('Employee Terminated Successfully.'));
    }

    public function reactivate(Employee $employee): RedirectResponse
    {
        $this->authorize('reactivate', $employee);

        try {
            $this->employees->reactivate($employee);
        } catch (DomainException $e) {
            return back()->with('error', $e->getMessage());
        }

        return back()->with('success', __('Employee Reactivated Successfully.'));
    }

    public function destroyImage(Employee $employee, Image $image): RedirectResponse
    {
        $this->authorize('update', $employee);

        abort_unless(
            $image->imageable_type === $employee->getMorphClass()
            && (int) $image->imageable_id === (int) $employee->id,
            404,
        );

        $this->images->delete($image);

        return back()->with('success', __('Image Removed Successfully.'));
    }
}
