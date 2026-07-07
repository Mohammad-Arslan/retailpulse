<?php

declare(strict_types=1);

namespace App\Http\Controllers\Admin;

use App\DTOs\Accounting\CreateCostCentreData;
use App\DTOs\Accounting\UpdateCostCentreData;
use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\Accounting\StoreCostCentreRequest;
use App\Http\Requests\Admin\Accounting\UpdateCostCentreRequest;
use App\Models\CostCentre;
use App\Services\Accounting\CostCentreService;
use DomainException;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

final class CostCentreController extends Controller
{
    public function __construct(
        private readonly CostCentreService $costCentreService,
    ) {}

    public function index(Request $request): Response
    {
        $this->authorize('viewAny', CostCentre::class);

        return Inertia::render('Admin/Accounting/CostCentres/Index', $this->costCentreService->indexPayload());
    }

    public function store(StoreCostCentreRequest $request): RedirectResponse
    {
        $this->authorize('create', CostCentre::class);

        $this->costCentreService->create(CreateCostCentreData::fromRequest($request));

        return back()->with('success', __('Cost centre created successfully.'));
    }

    public function update(UpdateCostCentreRequest $request, CostCentre $costCentre): RedirectResponse
    {
        $this->authorize('update', $costCentre);

        $this->costCentreService->update($costCentre, UpdateCostCentreData::fromRequest($request));

        return back()->with('success', __('Cost centre updated successfully.'));
    }

    public function destroy(CostCentre $costCentre): RedirectResponse
    {
        $this->authorize('delete', $costCentre);

        try {
            $this->costCentreService->delete($costCentre);
        } catch (DomainException $e) {
            return back()->withErrors(['cost_centre' => $e->getMessage()]);
        }

        return back()->with('success', __('Cost centre deleted successfully.'));
    }
}
