<?php

declare(strict_types=1);

namespace App\Http\Controllers\Admin\Hr;

use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\Hr\StoreHrEmploymentTypeRequest;
use App\Http\Requests\Admin\Hr\UpdateHrEmploymentTypeRequest;
use App\Models\HrEmploymentType;
use App\Services\Hr\HrEmploymentTypeService;
use App\Support\ListPagination;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

final class HrEmploymentTypeController extends Controller
{
    public function __construct(
        private readonly HrEmploymentTypeService $employmentTypes,
    ) {}

    public function index(Request $request): Response
    {
        $this->authorize('viewAny', HrEmploymentType::class);

        $filters = ListPagination::filters($request, ['search', 'status', 'legal_entity_id', 'sort', 'direction']);

        return Inertia::render(
            'Admin/Hr/EmploymentTypes/Index',
            $this->employmentTypes->indexPayload($filters, ListPagination::resolve($filters['per_page'])),
        );
    }

    public function store(StoreHrEmploymentTypeRequest $request): RedirectResponse
    {
        $this->authorize('create', HrEmploymentType::class);

        $validated = $request->validated();
        $this->employmentTypes->create([
            'legal_entity_id' => $validated['legal_entity_id'] ?? null,
            'code' => $validated['code'],
            'name' => $validated['name'],
            'status' => $validated['status'] ?? 'active',
        ]);

        return back()->with('success', __('Employment Type Created Successfully.'));
    }

    public function update(UpdateHrEmploymentTypeRequest $request, HrEmploymentType $employment_type): RedirectResponse
    {
        $this->authorize('update', $employment_type);

        $validated = $request->validated();
        $this->employmentTypes->update($employment_type, [
            'legal_entity_id' => $validated['legal_entity_id'] ?? null,
            'code' => $validated['code'],
            'name' => $validated['name'],
            'status' => $validated['status'] ?? $employment_type->status,
        ]);

        return back()->with('success', __('Employment Type Updated Successfully.'));
    }
}
