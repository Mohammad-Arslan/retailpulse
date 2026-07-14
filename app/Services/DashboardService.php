<?php

declare(strict_types=1);

namespace App\Services;

use App\Enums\ProductType;
use App\Enums\SaleStatus;
use App\Enums\StockTransferStatus;
use App\Models\Branch;
use App\Models\Brand;
use App\Models\Category;
use App\Models\Inventory;
use App\Models\Product;
use App\Models\ProductVariant;
use App\Models\Sale;
use App\Models\StockMovement;
use App\Models\StockTransfer;
use App\Models\Warehouse;
use Illuminate\Database\Eloquent\Builder;

final class DashboardService
{
    /**
     * @param  list<int>|null  $accessibleBranchIds
     * @return array{
     *     todays_sales: float,
     *     gross_profit: float,
     *     average_transaction_value: float,
     *     transaction_count: int,
     *     pending_approvals: int,
     * }
     */
    public function salesKpis(?int $branchId = null, ?array $accessibleBranchIds = null): array
    {
        $completed = $this->scopeSales(
            Sale::query()->where('status', SaleStatus::Completed)->where('is_historical', false),
            $branchId,
            $accessibleBranchIds,
        );

        $todayQuery = (clone $completed)->whereDate('completed_at', today());
        $todaysSales = (float) (clone $todayQuery)->toBase()->sum('grand_total');
        $todayCount = (int) (clone $todayQuery)->toBase()->count('*');

        $grossProfit = (float) (clone $todayQuery)
            ->get(['subtotal', 'total_discount'])
            ->sum(fn (Sale $sale): float => (float) $sale->subtotal - (float) $sale->total_discount);

        $layawayPending = (int) $this->scopeSales(
            Sale::query()->where('status', SaleStatus::PartiallyPaid)->where('is_historical', false),
            $branchId,
            $accessibleBranchIds,
        )->toBase()->count('*');

        return [
            'todays_sales' => round($todaysSales, 2),
            'gross_profit' => round($grossProfit, 2),
            'average_transaction_value' => $todayCount > 0
                ? round($todaysSales / $todayCount, 2)
                : 0.0,
            'transaction_count' => $todayCount,
            'pending_approvals' => $layawayPending,
        ];
    }

    /**
     * @param  list<int>|null  $accessibleBranchIds
     * @return array{
     *     wow_revenue: list<array{label: string, amount: float}>,
     *     mom_revenue: list<array{label: string, amount: float}>,
     * }
     */
    public function revenueCharts(?int $branchId = null, ?array $accessibleBranchIds = null): array
    {
        $wowStart = now()->subDays(6)->startOfDay();

        $wowSales = $this->scopeSales(
            Sale::query()
                ->where('status', SaleStatus::Completed)
                ->where('is_historical', false)
                ->where('completed_at', '>=', $wowStart),
            $branchId,
            $accessibleBranchIds,
        )->get(['completed_at', 'grand_total']);

        $wow = collect(range(0, 6))->map(function (int $offset) use ($wowStart, $wowSales): array {
            $date = $wowStart->copy()->addDays($offset);

            $amount = $wowSales
                ->filter(fn (Sale $sale): bool => $sale->completed_at?->isSameDay($date) ?? false)
                ->sum(fn (Sale $sale): float => (float) $sale->grand_total);

            return [
                'label' => $date->format('M j'),
                'amount' => round($amount, 2),
            ];
        })->all();

        $momStart = now()->subMonths(5)->startOfMonth();

        $momSales = $this->scopeSales(
            Sale::query()
                ->where('status', SaleStatus::Completed)
                ->where('is_historical', false)
                ->where('completed_at', '>=', $momStart),
            $branchId,
            $accessibleBranchIds,
        )->get(['completed_at', 'grand_total']);

        $mom = collect(range(0, 5))->map(function (int $offset) use ($momStart, $momSales): array {
            $date = $momStart->copy()->addMonths($offset);

            $amount = $momSales
                ->filter(fn (Sale $sale): bool => $sale->completed_at?->format('Y-m') === $date->format('Y-m'))
                ->sum(fn (Sale $sale): float => (float) $sale->grand_total);

            return [
                'label' => $date->format('M Y'),
                'amount' => round($amount, 2),
            ];
        })->all();

        return [
            'wow_revenue' => $wow,
            'mom_revenue' => $mom,
        ];
    }

    /**
     * @param  list<int>|null  $accessibleBranchIds
     * @return array<string, mixed>
     */
    public function inventoryHealth(?int $branchId = null, ?array $accessibleBranchIds = null): array
    {
        $inventoryQuery = $this->scopedInventoryQuery($branchId, $accessibleBranchIds);

        $lowStockLines = (int) (clone $inventoryQuery)
            ->join('product_variants', 'inventories.product_variant_id', '=', 'product_variants.id', 'inner', false)
            ->join('products', 'product_variants.product_id', '=', 'products.id', 'inner', false)
            ->whereNotNull('product_variants.reorder_point')
            ->whereNotIn('products.type', [
                ProductType::Service->value,
                ProductType::Digital->value,
            ])
            ->whereRaw(
                'GREATEST(0, inventories.quantity_on_hand - inventories.quantity_reserved) <= product_variants.reorder_point',
            )
            ->toBase()
            ->count('*');

        $criticalLowStockLines = (int) (clone $inventoryQuery)
            ->join('product_variants', 'inventories.product_variant_id', '=', 'product_variants.id', 'inner', false)
            ->join('products', 'product_variants.product_id', '=', 'products.id', 'inner', false)
            ->join('warehouses', 'inventories.warehouse_id', '=', 'warehouses.id', 'inner', false)
            ->join('variant_branch_settings', function ($join) {
                $join->on('variant_branch_settings.product_variant_id', '=', 'inventories.product_variant_id')
                    ->on('variant_branch_settings.branch_id', '=', 'warehouses.branch_id');
            })
            ->whereNotNull('variant_branch_settings.safety_stock_qty')
            ->whereNotIn('products.type', [
                ProductType::Service->value,
                ProductType::Digital->value,
            ])
            ->whereRaw(
                'GREATEST(0, inventories.quantity_on_hand - inventories.quantity_reserved - inventories.quantity_in_quarantine) <= variant_branch_settings.safety_stock_qty',
            )
            ->toBase()
            ->count('*');

        $transferQuery = $this->scopedTransferQuery($branchId, $accessibleBranchIds);
        $movementQuery = $this->scopedMovementQuery($branchId, $accessibleBranchIds);

        return [
            'units_on_hand' => (int) (clone $inventoryQuery)->toBase()->sum('quantity_on_hand'),
            'low_stock_lines' => $lowStockLines,
            'critical_low_stock_lines' => $criticalLowStockLines,
            'stock_movements_today' => (int) (clone $movementQuery)
                ->whereDate('created_at', '=', today(), 'and')
                ->toBase()
                ->count('*'),
            'transfers_draft' => (int) (clone $transferQuery)
                ->where('status', StockTransferStatus::Draft)
                ->toBase()
                ->count('*'),
            'transfers_in_transit' => (int) (clone $transferQuery)
                ->where('status', StockTransferStatus::Shipped)
                ->toBase()
                ->count('*'),
            'stock_movement_trend' => $this->stockMovementTrendSeries($branchId, $accessibleBranchIds),
        ];
    }

    /**
     * @param  list<int>|null  $accessibleBranchIds
     * @return array<string, mixed>
     */
    public function operationsOverview(?int $branchId = null, ?array $accessibleBranchIds = null): array
    {
        $branchQuery = Branch::query();
        if ($branchId !== null) {
            $branchQuery->whereKey($branchId);
        } elseif ($accessibleBranchIds !== null) {
            $branchQuery->whereIn('id', $accessibleBranchIds);
        }

        $warehouseQuery = Warehouse::query();
        if ($branchId !== null) {
            $warehouseQuery->where('branch_id', $branchId);
        } elseif ($accessibleBranchIds !== null) {
            $warehouseQuery->whereIn('branch_id', $accessibleBranchIds);
        }

        return [
            'branches_active' => (int) (clone $branchQuery)->where('is_active', true)->toBase()->count('*'),
            'branches_total' => (int) (clone $branchQuery)->toBase()->count('*'),
            'warehouses' => (int) (clone $warehouseQuery)->toBase()->count('*'),
            'products_active' => (int) Product::query()->where('is_active', true)->toBase()->count('*'),
            'product_variants' => (int) ProductVariant::query()->toBase()->count('*'),
            'categories' => (int) Category::query()->toBase()->count('*'),
            'brands' => (int) Brand::query()->toBase()->count('*'),
            'branches_preview' => $this->branchesPreview($branchId, $accessibleBranchIds),
        ];
    }

    /**
     * @param  Builder<Sale>  $query
     * @param  list<int>|null  $accessibleBranchIds
     * @return Builder<Sale>
     */
    private function scopeSales(Builder $query, ?int $branchId, ?array $accessibleBranchIds): Builder
    {
        if ($branchId !== null) {
            return $query->where('branch_id', $branchId);
        }

        if ($accessibleBranchIds !== null) {
            return $query->whereIn('branch_id', $accessibleBranchIds);
        }

        return $query;
    }

    /**
     * @param  list<int>|null  $accessibleBranchIds
     * @return Builder<Inventory>
     */
    private function scopedInventoryQuery(?int $branchId, ?array $accessibleBranchIds): Builder
    {
        return Inventory::query()
            ->when($branchId !== null || $accessibleBranchIds !== null, function (Builder $query) use ($branchId, $accessibleBranchIds): void {
                $query->whereHas('warehouse', function (Builder $warehouse) use ($branchId, $accessibleBranchIds): void {
                    if ($branchId !== null) {
                        $warehouse->where('branch_id', $branchId);
                    } elseif ($accessibleBranchIds !== null) {
                        $warehouse->whereIn('branch_id', $accessibleBranchIds);
                    }
                });
            });
    }

    /**
     * @param  list<int>|null  $accessibleBranchIds
     * @return Builder<StockTransfer>
     */
    private function scopedTransferQuery(?int $branchId, ?array $accessibleBranchIds): Builder
    {
        return StockTransfer::query()
            ->when($branchId !== null || $accessibleBranchIds !== null, function (Builder $query) use ($branchId, $accessibleBranchIds): void {
                $query->where(function (Builder $q) use ($branchId, $accessibleBranchIds): void {
                    $q->whereHas('fromWarehouse', function (Builder $w) use ($branchId, $accessibleBranchIds): void {
                        if ($branchId !== null) {
                            $w->where('branch_id', $branchId);
                        } else {
                            $w->whereIn('branch_id', $accessibleBranchIds ?? []);
                        }
                    })->orWhereHas('toWarehouse', function (Builder $w) use ($branchId, $accessibleBranchIds): void {
                        if ($branchId !== null) {
                            $w->where('branch_id', $branchId);
                        } else {
                            $w->whereIn('branch_id', $accessibleBranchIds ?? []);
                        }
                    });
                });
            });
    }

    /**
     * @param  list<int>|null  $accessibleBranchIds
     * @return Builder<StockMovement>
     */
    private function scopedMovementQuery(?int $branchId, ?array $accessibleBranchIds): Builder
    {
        return StockMovement::query()
            ->when($branchId !== null || $accessibleBranchIds !== null, function (Builder $query) use ($branchId, $accessibleBranchIds): void {
                $query->whereHas('warehouse', function (Builder $warehouse) use ($branchId, $accessibleBranchIds): void {
                    if ($branchId !== null) {
                        $warehouse->where('branch_id', $branchId);
                    } else {
                        $warehouse->whereIn('branch_id', $accessibleBranchIds ?? []);
                    }
                });
            });
    }

    /**
     * @param  list<int>|null  $accessibleBranchIds
     * @return list<array{label: string, count: int}>
     */
    private function stockMovementTrendSeries(
        ?int $branchId = null,
        ?array $accessibleBranchIds = null,
        int $days = 7,
    ): array {
        $start = now()->subDays($days - 1)->startOfDay();
        $query = $this->scopedMovementQuery($branchId, $accessibleBranchIds)
            ->where('created_at', '>=', $start);

        $counts = $query
            ->selectRaw('DATE(created_at) as day, COUNT(*) as total')
            ->groupBy('day')
            ->pluck('total', 'day');

        return collect(range(0, $days - 1))
            ->map(function (int $offset) use ($start, $counts): array {
                $date = $start->copy()->addDays($offset);
                $key = $date->toDateString();

                return [
                    'label' => $date->format('M j'),
                    'count' => (int) ($counts[$key] ?? 0),
                ];
            })
            ->all();
    }

    /**
     * @param  list<int>|null  $accessibleBranchIds
     * @return list<array{id: int, name: string, code: string, warehouses_count: int, users_count: int}>
     */
    private function branchesPreview(
        ?int $branchId = null,
        ?array $accessibleBranchIds = null,
        int $limit = 6,
    ): array {
        $query = Branch::query()->where('is_active', true);

        if ($branchId !== null) {
            $query->whereKey($branchId);
        } elseif ($accessibleBranchIds !== null) {
            $query->whereIn('id', $accessibleBranchIds);
        }

        return $query
            ->withCount(['warehouses', 'users'])
            ->orderBy('name')
            ->limit($limit)
            ->get()
            ->map(fn (Branch $branch): array => [
                'id' => $branch->id,
                'name' => $branch->name,
                'code' => $branch->code,
                'warehouses_count' => (int) $branch->warehouses_count,
                'users_count' => (int) $branch->users_count,
            ])
            ->all();
    }
}
