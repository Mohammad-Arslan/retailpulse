<?php

declare(strict_types=1);

namespace App\Http\Controllers\Admin;

use App\DTOs\Unit\CreateUnitData;
use App\DTOs\Unit\UpdateUnitData;
use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\StoreUnitRequest;
use App\Http\Requests\Admin\UpdateUnitRequest;
use App\Models\Unit;
use App\Repositories\Contracts\UnitRepositoryInterface;
use App\Services\UnitService;
use App\Support\ListPagination;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

final class UnitController extends Controller
{
    public function __construct(
        private readonly UnitRepositoryInterface $units,
        private readonly UnitService $unitService,
    ) {}

    public function index(Request $request): Response
    {
        $this->authorize('viewAny', Unit::class);

        $filters = ListPagination::filters(
            $request,
            ['search', 'is_active', 'sort', 'direction'],
        );

        return Inertia::render('Admin/Units/Index', [
            'units' => $this->units->paginate(
                $filters,
                ListPagination::resolve($filters['per_page']),
            ),
            'filters' => $filters,
        ]);
    }

    public function create(): Response
    {
        $this->authorize('create', Unit::class);

        return Inertia::render('Admin/Units/Create');
    }

    public function store(StoreUnitRequest $request): RedirectResponse
    {
        $this->authorize('create', Unit::class);

        $unit = $this->unitService->create(CreateUnitData::fromRequest($request));

        return redirect()
            ->route('admin.units.edit', $unit)
            ->with('success', __('Unit created successfully.'));
    }

    public function edit(Unit $unit): Response
    {
        $this->authorize('update', $unit);

        return Inertia::render('Admin/Units/Edit', [
            'unit' => [
                'id' => $unit->id,
                'name' => $unit->name,
                'abbreviation' => $unit->abbreviation,
                'is_active' => $unit->is_active,
            ],
        ]);
    }

    public function update(UpdateUnitRequest $request, Unit $unit): RedirectResponse
    {
        $this->authorize('update', $unit);

        $this->unitService->update($unit, UpdateUnitData::fromRequest($request));

        return redirect()
            ->route('admin.units.edit', $unit)
            ->with('success', __('Unit updated successfully.'));
    }

    public function destroy(Unit $unit): RedirectResponse
    {
        $this->authorize('delete', $unit);

        $this->unitService->delete($unit);

        return redirect()
            ->route('admin.units.index')
            ->with('success', __('Unit deleted successfully.'));
    }
}
