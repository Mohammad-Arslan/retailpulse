<?php

declare(strict_types=1);

namespace App\Http\Controllers\Admin\Hr;

use App\DTOs\Hr\CreateDepartmentData;
use App\DTOs\Hr\UpdateDepartmentData;
use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\Hr\StoreDepartmentRequest;
use App\Http\Requests\Admin\Hr\UpdateDepartmentRequest;
use App\Models\Department;
use App\Services\Hr\DepartmentService;
use App\Support\ListPagination;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

final class DepartmentController extends Controller
{
    public function __construct(
        private readonly DepartmentService $departments,
    ) {}

    public function index(Request $request): Response
    {
        $this->authorize('viewAny', Department::class);

        $filters = ListPagination::filters($request, ['search', 'status', 'legal_entity_id', 'sort', 'direction']);

        return Inertia::render(
            'Admin/Hr/Departments/Index',
            $this->departments->indexPayload($filters, ListPagination::resolve($filters['per_page'])),
        );
    }

    public function store(StoreDepartmentRequest $request): RedirectResponse
    {
        $this->authorize('create', Department::class);

        $this->departments->create(CreateDepartmentData::fromRequest($request));

        return back()->with('success', __('Department Created Successfully.'));
    }

    public function update(UpdateDepartmentRequest $request, Department $department): RedirectResponse
    {
        $this->authorize('update', $department);

        $this->departments->update($department, UpdateDepartmentData::fromRequest($request));

        return back()->with('success', __('Department Updated Successfully.'));
    }
}
