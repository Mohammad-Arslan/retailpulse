<?php

declare(strict_types=1);

namespace App\Repositories\Eloquent;

use App\Enums\StockTransferStatus;
use App\Models\StockTransfer;
use App\Models\Warehouse;
use App\Repositories\Contracts\WarehouseRepositoryInterface;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;

final class WarehouseRepository implements WarehouseRepositoryInterface
{
    public function paginateForBranch(array $filters = [], ?array $accessibleBranchIds = null, int $perPage = 15): LengthAwarePaginator
    {
        $query = Warehouse::query()
            ->with('branch:id,name,code')
            ->withCount([
                'inventories as on_hand_lines' => fn ($q) => $q->where('quantity_on_hand', '>', 0),
            ]);

        if ($accessibleBranchIds !== null) {
            $query->whereIn('branch_id', $accessibleBranchIds);
        }

        if (! empty($filters['branch_id'])) {
            $query->where('branch_id', (int) $filters['branch_id']);
        }

        $sort = $filters['sort'] ?? 'name';
        $direction = strtolower((string) ($filters['direction'] ?? 'asc')) === 'desc' ? 'desc' : 'asc';
        $allowedSorts = ['name', 'code', 'created_at', 'is_active', 'is_default'];

        if (! in_array($sort, $allowedSorts, true)) {
            $sort = 'name';
        }

        $query->orderBy($sort, $direction);

        if (! empty($filters['search'])) {
            $search = $filters['search'];
            $query->where(function ($q) use ($search) {
                $q->where('name', 'like', "%{$search}%")
                    ->orWhere('code', 'like', "%{$search}%");
            });
        }

        if (isset($filters['is_active'])) {
            $query->where('is_active', filter_var($filters['is_active'], FILTER_VALIDATE_BOOLEAN));
        }

        return $query->paginate($perPage)->withQueryString();
    }

    public function findById(int $id): ?Warehouse
    {
        return Warehouse::query()->with('branch:id,name,code')->find($id);
    }

    public function create(array $attributes): Warehouse
    {
        return Warehouse::query()->create($attributes);
    }

    public function update(Warehouse $warehouse, array $attributes): Warehouse
    {
        $warehouse->update($attributes);

        return $warehouse->fresh(['branch']);
    }

    public function setDefaultForBranch(int $branchId, int $warehouseId): void
    {
        Warehouse::query()
            ->where('branch_id', $branchId)
            ->update(['is_default' => false]);

        Warehouse::query()
            ->whereKey($warehouseId)
            ->where('branch_id', $branchId)
            ->update(['is_default' => true]);
    }

    public function clearDefaultForBranch(int $branchId): void
    {
        Warehouse::query()
            ->where('branch_id', $branchId)
            ->update(['is_default' => false]);
    }

    public function countActiveForBranch(int $branchId): int
    {
        return Warehouse::query()
            ->where('branch_id', $branchId)
            ->where('is_active', true)
            ->count();
    }

    public function hasOnHandStock(Warehouse $warehouse): bool
    {
        return $warehouse->inventories()
            ->where('quantity_on_hand', '>', 0)
            ->exists();
    }

    public function hasOpenTransfers(Warehouse $warehouse): bool
    {
        $openStatuses = [
            StockTransferStatus::Draft,
            StockTransferStatus::Shipped,
            StockTransferStatus::PartiallyReceived,
        ];

        return StockTransfer::query()
            ->where(function ($q) use ($warehouse) {
                $q->where('from_warehouse_id', $warehouse->id)
                    ->orWhere('to_warehouse_id', $warehouse->id);
            })
            ->whereIn('status', $openStatuses)
            ->exists();
    }

    public function firstActiveForBranch(int $branchId, ?int $exceptId = null): ?Warehouse
    {
        return Warehouse::query()
            ->where('branch_id', $branchId)
            ->where('is_active', true)
            ->when($exceptId !== null, fn ($q) => $q->whereKeyNot($exceptId))
            ->orderBy('name')
            ->first();
    }

    public function codeExistsForBranch(int $branchId, string $code, ?int $exceptId = null): bool
    {
        return Warehouse::query()
            ->where('branch_id', $branchId)
            ->where('code', strtoupper($code))
            ->when($exceptId !== null, fn ($q) => $q->whereKeyNot($exceptId))
            ->exists();
    }

    public function activeOptionsForBranch(int $branchId): array
    {
        return Warehouse::query()
            ->where('branch_id', $branchId)
            ->where('is_active', true)
            ->orderByDesc('is_default')
            ->orderBy('name')
            ->get(['id', 'name', 'code', 'is_default', 'is_active'])
            ->map(fn (Warehouse $warehouse) => [
                'id' => $warehouse->id,
                'name' => $warehouse->name,
                'code' => $warehouse->code,
                'is_default' => $warehouse->is_default,
                'is_active' => $warehouse->is_active,
            ])
            ->values()
            ->all();
    }

    public function allActiveForPicker(?array $accessibleBranchIds = null): array
    {
        $query = Warehouse::query()
            ->with('branch:id,name,code')
            ->where('is_active', true)
            ->orderBy('name');

        if ($accessibleBranchIds !== null) {
            $query->whereIn('branch_id', $accessibleBranchIds);
        }

        return $query
            ->get(['id', 'branch_id', 'name', 'code'])
            ->map(fn (Warehouse $warehouse) => [
                'id' => $warehouse->id,
                'name' => $warehouse->name,
                'code' => $warehouse->code,
                'branch_name' => $warehouse->branch?->name ?? '',
                'branch_code' => $warehouse->branch?->code ?? '',
                'label' => sprintf(
                    '%s (%s) — %s',
                    $warehouse->name,
                    $warehouse->branch?->code ?? '',
                    $warehouse->code,
                ),
            ])
            ->values()
            ->all();
    }
}
