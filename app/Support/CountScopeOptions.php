<?php

declare(strict_types=1);

namespace App\Support;

use App\Models\Category;
use App\Models\WarehouseZone;
use App\Services\BranchContextService;
use Illuminate\Http\Request;

final class CountScopeOptions
{
    /**
     * @return array{
     *     zonesByWarehouse: array<int|string, list<array{id: int, name: string, code: string}>>,
     *     categories: list<array{id: int, name: string}>,
     *     varianceDefaults: array{pct: float, value: float}
     * }
     */
    public static function forRequest(Request $request, BranchContextService $branchContext): array
    {
        $branchId = app(BranchContext::class)->branchId;
        $accessibleIds = $branchContext->accessibleBranchIds($request->user());

        $zonesByWarehouse = WarehouseZone::query()
            ->where('is_active', true)
            ->whereHas('warehouse', function ($q) use ($branchId, $accessibleIds) {
                $q->where('is_active', true);

                if ($branchId !== null) {
                    $q->where('branch_id', $branchId);
                }

                if ($accessibleIds !== null) {
                    $q->whereIn('branch_id', $accessibleIds);
                }
            })
            ->orderBy('name')
            ->get(['id', 'warehouse_id', 'name', 'code'])
            ->groupBy('warehouse_id')
            ->map(fn ($zones) => $zones->map(fn (WarehouseZone $zone) => [
                'id' => $zone->id,
                'name' => $zone->name,
                'code' => $zone->code,
            ])->values()->all())
            ->all();

        $categories = Category::query()
            ->where('is_active', true)
            ->orderBy('name')
            ->get(['id', 'name'])
            ->map(fn (Category $category) => [
                'id' => $category->id,
                'name' => $category->name,
            ])
            ->values()
            ->all();

        return [
            'zonesByWarehouse' => $zonesByWarehouse,
            'categories' => $categories,
            'varianceDefaults' => [
                'pct' => (float) config('inventory.count_variance_threshold_pct', 5),
                'value' => (float) config('inventory.count_variance_threshold_value', 1000),
            ],
        ];
    }
}
