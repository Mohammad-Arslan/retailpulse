<?php

declare(strict_types=1);

namespace App\Http\Controllers\Admin\Hr;

use App\DTOs\Hr\CreateDesignationData;
use App\DTOs\Hr\UpdateDesignationData;
use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\Hr\StoreDesignationRequest;
use App\Http\Requests\Admin\Hr\UpdateDesignationRequest;
use App\Models\Designation;
use App\Services\Hr\DesignationService;
use App\Support\ListPagination;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

final class DesignationController extends Controller
{
    public function __construct(
        private readonly DesignationService $designations,
    ) {}

    public function index(Request $request): Response
    {
        $this->authorize('viewAny', Designation::class);

        $filters = ListPagination::filters($request, ['search', 'status', 'legal_entity_id', 'sort', 'direction']);

        return Inertia::render(
            'Admin/Hr/Designations/Index',
            $this->designations->indexPayload($filters, ListPagination::resolve($filters['per_page'])),
        );
    }

    public function store(StoreDesignationRequest $request): RedirectResponse
    {
        $this->authorize('create', Designation::class);

        $this->designations->create(CreateDesignationData::fromRequest($request));

        return back()->with('success', __('Designation Created Successfully.'));
    }

    public function update(UpdateDesignationRequest $request, Designation $designation): RedirectResponse
    {
        $this->authorize('update', $designation);

        $this->designations->update($designation, UpdateDesignationData::fromRequest($request));

        return back()->with('success', __('Designation Updated Successfully.'));
    }
}
