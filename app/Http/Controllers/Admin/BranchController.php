<?php

declare(strict_types=1);

namespace App\Http\Controllers\Admin;

use App\DTOs\Branch\CreateBranchData;
use App\DTOs\Branch\UpdateBranchData;
use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\StoreBranchRequest;
use App\Http\Requests\Admin\UpdateBranchRequest;
use App\Models\Branch;
use App\Repositories\Contracts\BranchRepositoryInterface;
use App\Repositories\Contracts\WarehouseRepositoryInterface;
use App\Services\BranchContextService;
use App\Services\BranchService;
use App\Support\BranchCodeGenerator;
use App\Support\BranchOperationalOptions;
use App\Support\ListPagination;
use App\Support\OperatingHours;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

final class BranchController extends Controller
{
    public function __construct(
        private readonly BranchRepositoryInterface $branches,
        private readonly BranchService $branchService,
        private readonly BranchContextService $branchContext,
        private readonly WarehouseRepositoryInterface $warehouseRepository,
        private readonly BranchCodeGenerator $codeGenerator,
    ) {}

    public function index(Request $request): Response
    {
        $this->authorize('viewAny', Branch::class);

        $accessibleIds = $this->branchContext->accessibleBranchIds($request->user());

        $filters = ListPagination::filters(
            $request,
            ['search', 'is_active', 'sort', 'direction'],
        );

        return Inertia::render('Admin/Branches/Index', [
            'branches' => $this->branches->paginate(
                $filters,
                $accessibleIds,
                ListPagination::resolve($filters['per_page']),
            ),
            'filters' => $filters,
        ]);
    }

    public function create(Request $request): Response
    {
        $this->authorize('create', Branch::class);

        $accessibleIds = $this->branchContext->accessibleBranchIds($request->user());

        return Inertia::render('Admin/Branches/Create', [
            'defaultOperatingHours' => OperatingHours::defaults(),
            'operationalOptions' => BranchOperationalOptions::formPayload(),
            'warehousePicker' => $this->warehouseRepository->allActiveForPicker($accessibleIds),
        ]);
    }

    public function suggestCode(Request $request): JsonResponse
    {
        $this->authorize('create', Branch::class);

        $validated = $request->validate([
            'name' => ['required', 'string', 'max:255'],
        ]);

        return response()->json([
            'code' => $this->codeGenerator->generate($validated['name']),
            'preview' => false,
        ]);
    }

    public function store(StoreBranchRequest $request): RedirectResponse
    {
        $this->authorize('create', Branch::class);

        $branch = $this->branchService->create(CreateBranchData::fromRequest($request));

        return redirect()
            ->route('admin.branches.edit', $branch)
            ->with('success', __('Branch created successfully.'));
    }

    public function edit(Branch $branch): Response
    {
        $this->authorize('update', $branch);

        $branch->load('warehouses');

        return Inertia::render('Admin/Branches/Edit', [
            'branch' => [
                'id' => $branch->id,
                'name' => $branch->name,
                'code' => $branch->code,
                'address' => $branch->address,
                'currency' => $branch->currency,
                'timezone' => $branch->timezone,
                'picking_strategy' => $branch->picking_strategy?->value ?? 'fifo',
                'operating_hours' => $branch->operating_hours ?? OperatingHours::defaults(),
                'weekend_days' => $branch->weekend_days,
                'receipt_footer' => $branch->receipt_footer,
                'is_active' => $branch->is_active,
                'cutover_date' => $branch->cutover_date?->format('Y-m-d\TH:i'),
                'warehouses' => $branch->warehouses->map(fn ($w) => [
                    'id' => $w->id,
                    'name' => $w->name,
                    'code' => $w->code,
                    'is_default' => $w->is_default,
                    'is_active' => $w->is_active,
                ]),
                'default_warehouse_id' => $branch->warehouses->firstWhere('is_default', true)?->id,
            ],
            'warehouseOptions' => collect($this->warehouseRepository->activeOptionsForBranch($branch->id))
                ->map(fn (array $warehouse) => [
                    'value' => (string) $warehouse['id'],
                    'label' => sprintf('%s (%s)', $warehouse['name'], $warehouse['code']),
                ])
                ->values()
                ->all(),
            'operationalOptions' => BranchOperationalOptions::formPayload(),
        ]);
    }

    public function update(UpdateBranchRequest $request, Branch $branch): RedirectResponse
    {
        $this->authorize('update', $branch);

        $this->branchService->update($branch, UpdateBranchData::fromRequest($request));

        return redirect()
            ->route('admin.branches.edit', $branch)
            ->with('success', __('Branch updated successfully.'));
    }

    public function destroy(Branch $branch): RedirectResponse
    {
        $this->authorize('delete', $branch);

        $this->branchService->delete($branch);

        return redirect()
            ->route('admin.branches.index')
            ->with('success', __('Branch deleted successfully.'));
    }
}
