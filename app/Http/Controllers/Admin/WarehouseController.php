<?php

declare(strict_types=1);

namespace App\Http\Controllers\Admin;

use App\DTOs\Warehouse\CreateWarehouseData;
use App\DTOs\Warehouse\UpdateWarehouseData;
use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\StoreWarehouseRequest;
use App\Http\Requests\Admin\UpdateWarehouseRequest;
use App\Models\Warehouse;
use App\Repositories\Contracts\BranchRepositoryInterface;
use App\Repositories\Contracts\WarehouseRepositoryInterface;
use App\Services\BranchContextService;
use App\Services\WarehouseService;
use App\Support\ListPagination;
use App\Support\WarehouseCodeGenerator;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;
use Inertia\Inertia;
use Inertia\Response;

final class WarehouseController extends Controller
{
    public function __construct(
        private readonly WarehouseRepositoryInterface $warehouses,
        private readonly WarehouseService $warehouseService,
        private readonly BranchRepositoryInterface $branches,
        private readonly BranchContextService $branchContext,
        private readonly WarehouseCodeGenerator $codeGenerator,
    ) {}

    public function index(Request $request): Response
    {
        $this->authorize('viewAny', Warehouse::class);

        $accessibleIds = $this->branchContext->accessibleBranchIds($request->user());

        $filters = ListPagination::filters(
            $request,
            ['search', 'branch_id', 'is_active', 'sort', 'direction'],
        );

        return Inertia::render('Admin/Warehouses/Index', [
            'warehouses' => $this->warehouses->paginateForBranch(
                $filters,
                $accessibleIds,
                ListPagination::resolve($filters['per_page']),
            ),
            'filters' => $filters,
            'branches' => $this->branches->allActive($accessibleIds)
                ->map(fn ($branch) => [
                    'id' => $branch->id,
                    'name' => $branch->name,
                    'code' => $branch->code,
                ])
                ->values()
                ->all(),
        ]);
    }

    public function create(Request $request): Response
    {
        $this->authorize('create', Warehouse::class);

        $accessibleIds = $this->branchContext->accessibleBranchIds($request->user());

        return Inertia::render('Admin/Warehouses/Create', [
            'branches' => $this->branches->allActive($accessibleIds)
                ->map(fn ($branch) => [
                    'id' => $branch->id,
                    'name' => $branch->name,
                    'code' => $branch->code,
                ])
                ->values()
                ->all(),
            'defaultBranchId' => $this->resolveDefaultBranchId($request, $accessibleIds),
        ]);
    }

    /**
     * @param  list<int>|null  $accessibleIds
     */
    private function resolveDefaultBranchId(Request $request, ?array $accessibleIds): ?int
    {
        $branchId = $request->integer('branch_id');

        if ($branchId <= 0) {
            return null;
        }

        if ($accessibleIds !== null && ! in_array($branchId, $accessibleIds, true)) {
            return null;
        }

        return $branchId;
    }

    public function suggestCode(Request $request): JsonResponse
    {
        $user = $request->user();

        if ($user === null || (! $user->can('warehouses.create') && ! $user->can('branches.create'))) {
            abort(403);
        }

        $validated = $request->validate([
            'branch_id' => ['nullable', 'integer', Rule::exists('branches', 'id')],
            'name' => ['required', 'string', 'max:255'],
        ]);

        $branchId = isset($validated['branch_id']) ? (int) $validated['branch_id'] : null;
        $name = $validated['name'];

        if ($branchId === null || $branchId <= 0) {
            return response()->json([
                'code' => WarehouseCodeGenerator::previewFromName($name),
                'preview' => true,
            ]);
        }

        $accessibleIds = $this->branchContext->accessibleBranchIds($request->user());

        if ($accessibleIds !== null && ! in_array($branchId, $accessibleIds, true)) {
            abort(403);
        }

        return response()->json([
            'code' => $this->codeGenerator->generate($branchId, $name),
            'preview' => false,
        ]);
    }

    public function store(StoreWarehouseRequest $request): RedirectResponse
    {
        $this->authorize('create', Warehouse::class);

        $warehouse = $this->warehouseService->create(CreateWarehouseData::fromRequest($request));

        return redirect()
            ->route('admin.warehouses.edit', $warehouse)
            ->with('success', __('Warehouse created successfully.'));
    }

    public function edit(Warehouse $warehouse): Response
    {
        $this->authorize('update', $warehouse);

        $warehouse->load('branch:id,name,code');

        return Inertia::render('Admin/Warehouses/Edit', [
            'warehouse' => [
                'id' => $warehouse->id,
                'branch_id' => $warehouse->branch_id,
                'branch_name' => $warehouse->branch?->name ?? '',
                'branch_code' => $warehouse->branch?->code ?? '',
                'name' => $warehouse->name,
                'code' => $warehouse->code,
                'is_default' => $warehouse->is_default,
                'is_active' => $warehouse->is_active,
            ],
        ]);
    }

    public function update(UpdateWarehouseRequest $request, Warehouse $warehouse): RedirectResponse
    {
        $this->authorize('update', $warehouse);

        $this->warehouseService->update($warehouse, UpdateWarehouseData::fromRequest($request));

        return redirect()
            ->route('admin.warehouses.edit', $warehouse)
            ->with('success', __('Warehouse updated successfully.'));
    }

    public function deactivate(Warehouse $warehouse): RedirectResponse
    {
        $this->authorize('deactivate', $warehouse);

        try {
            $this->warehouseService->deactivate($warehouse);
        } catch (ValidationException $e) {
            return redirect()
                ->back()
                ->withErrors($e->errors());
        }

        return redirect()
            ->route('admin.warehouses.index')
            ->with('success', __('Warehouse deactivated successfully.'));
    }
}
