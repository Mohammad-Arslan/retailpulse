<?php

declare(strict_types=1);

namespace App\Http\Controllers\Admin;

use App\DTOs\BinLocation\BinTransferData;
use App\DTOs\BinLocation\CreateBinLocationData;
use App\DTOs\BinLocation\CreateWarehouseZoneData;
use App\DTOs\BinLocation\UpdateBinLocationData;
use App\DTOs\BinLocation\UpdateWarehouseZoneData;
use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\BinTransferRequest;
use App\Http\Requests\Admin\StoreBinLocationRequest;
use App\Http\Requests\Admin\StoreWarehouseZoneRequest;
use App\Http\Requests\Admin\UpdateBinLocationRequest;
use App\Http\Requests\Admin\UpdateWarehouseZoneRequest;
use App\Models\BinLocation;
use App\Models\Warehouse;
use App\Models\WarehouseZone;
use App\Repositories\Contracts\BinLocationRepositoryInterface;
use App\Services\BinLocationService;
use Illuminate\Http\RedirectResponse;
use Inertia\Inertia;
use Inertia\Response;

final class WarehouseBinController extends Controller
{
    public function __construct(
        private readonly BinLocationRepositoryInterface $bins,
        private readonly BinLocationService $binService,
    ) {}

    public function index(Warehouse $warehouse): Response
    {
        $this->authorize('manageBins', $warehouse);

        $warehouse->load('branch:id,name,code');

        return Inertia::render('Admin/Warehouses/Bins/Index', [
            'warehouse' => [
                'id' => $warehouse->id,
                'name' => $warehouse->name,
                'code' => $warehouse->code,
                'branch_name' => $warehouse->branch?->name ?? '',
            ],
            'zones' => $this->bins->zonesForWarehouse($warehouse->id)
                ->map(fn (WarehouseZone $zone) => [
                    'id' => $zone->id,
                    'name' => $zone->name,
                    'code' => $zone->code,
                    'capacity_limit' => $zone->capacity_limit,
                    'is_active' => $zone->is_active,
                ])
                ->values()
                ->all(),
            'bins' => $this->bins->binsForWarehouse($warehouse->id)
                ->map(fn (BinLocation $bin) => [
                    'id' => $bin->id,
                    'warehouse_zone_id' => $bin->warehouse_zone_id,
                    'zone' => $bin->zone,
                    'aisle' => $bin->aisle,
                    'shelf' => $bin->shelf,
                    'bin_code' => $bin->bin_code,
                    'is_active' => $bin->is_active,
                    'capacity_limit' => $bin->capacity_limit,
                    'zone_name' => $bin->warehouseZone?->name,
                ])
                ->values()
                ->all(),
            'nextZoneCode' => $this->binService->nextZoneCode(),
            'nextBinCode' => $this->binService->nextBinCode(),
        ]);
    }

    public function storeZone(StoreWarehouseZoneRequest $request, Warehouse $warehouse): RedirectResponse
    {
        $this->authorize('manageBins', $warehouse);

        $zones = array_map(
            fn (array $row): CreateWarehouseZoneData => CreateWarehouseZoneData::fromArray($warehouse->id, $row),
            $request->validated('zones'),
        );

        $this->binService->createZones($zones);

        return redirect()
            ->route('admin.warehouses.bins.index', $warehouse)
            ->with('success', __('Zones created successfully.'));
    }

    public function updateZone(
        UpdateWarehouseZoneRequest $request,
        Warehouse $warehouse,
        WarehouseZone $zone,
    ): RedirectResponse {
        $this->authorize('manageBins', $warehouse);

        $this->binService->updateZone($zone, UpdateWarehouseZoneData::fromRequest($request));

        return redirect()
            ->route('admin.warehouses.bins.index', $warehouse)
            ->with('success', __('Zone updated successfully.'));
    }

    public function storeBin(StoreBinLocationRequest $request, Warehouse $warehouse): RedirectResponse
    {
        $this->authorize('manageBins', $warehouse);

        $bins = array_map(
            fn (array $row): CreateBinLocationData => CreateBinLocationData::fromArray($warehouse->id, $row),
            $request->validated('bins'),
        );

        $this->binService->createBins($bins);

        return redirect()
            ->route('admin.warehouses.bins.index', $warehouse)
            ->with('success', __('Bin locations created successfully.'));
    }

    public function updateBin(
        UpdateBinLocationRequest $request,
        Warehouse $warehouse,
        BinLocation $bin,
    ): RedirectResponse {
        $this->authorize('manageBins', $warehouse);

        $this->binService->updateBin($bin, UpdateBinLocationData::fromRequest($request));

        return redirect()
            ->route('admin.warehouses.bins.index', $warehouse)
            ->with('success', __('Bin location updated successfully.'));
    }

    public function transfer(BinTransferRequest $request): RedirectResponse
    {
        $this->authorize('manageBins', Warehouse::findOrFail($request->integer('warehouse_id')));

        $validated = $request->validated();

        $this->binService->transfer(new BinTransferData(
            warehouseId: (int) $validated['warehouse_id'],
            fromBinId: (int) $validated['from_bin_id'],
            toBinId: (int) $validated['to_bin_id'],
            variantId: (int) $validated['product_variant_id'],
            batchId: isset($validated['batch_id']) ? (int) $validated['batch_id'] : null,
            quantity: (int) $validated['quantity'],
            userId: $request->user()?->id,
            notes: $validated['notes'] ?? null,
        ));

        return redirect()
            ->route('admin.inventory.bin-transfer.form')
            ->with('success', __('Bin transfer completed successfully.'));
    }
}
