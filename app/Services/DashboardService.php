<?php

declare(strict_types=1);

namespace App\Services;

use App\Enums\ProductType;
use App\Enums\SaleStatus;
use App\Enums\StockTransferStatus;
use App\Models\AuditLog;
use App\Models\Branch;
use App\Models\Brand;
use App\Models\Category;
use App\Models\Inventory;
use App\Models\Permission;
use App\Models\Product;
use App\Models\ProductVariant;
use App\Models\Role;
use App\Models\Sale;
use App\Models\StockMovement;
use App\Models\StockTransfer;
use App\Models\User;
use App\Models\Warehouse;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\DB;

final class DashboardService
{
    /**
     * @param  list<int>|null  $accessibleBranchIds
     * @return array{
     *     users: int,
     *     roles: int,
     *     permissions: int,
     *     active_users: int,
     *     inactive_users: int,
     * }
     */
    public function stats(?int $branchId = null, ?array $accessibleBranchIds = null): array
    {
        $userQuery = $this->scopeUsers(User::query(), $branchId, $accessibleBranchIds);
        $users = (int) (clone $userQuery)->toBase()->count('*');
        $activeUsers = (int) (clone $userQuery)->where('is_active', true)->toBase()->count('*');

        return [
            'users' => $users,
            'roles' => $this->modelCount(Role::class),
            'permissions' => $this->modelCount(Permission::class),
            'active_users' => $activeUsers,
            'inactive_users' => max(0, $users - $activeUsers),
        ];
    }

    /**
     * @param  list<int>|null  $accessibleBranchIds
     * @return array{
     *     user_growth: list<array{label: string, count: int}>,
     *     users_by_role: list<array{role: string, count: int}>,
     *     permissions_by_group: list<array{group: string, count: int}>,
     *     user_status: list<array{status: string, count: int}>,
     * }
     */
    public function charts(?int $branchId = null, ?array $accessibleBranchIds = null): array
    {
        return [
            'user_growth' => $this->userGrowthSeries($branchId, $accessibleBranchIds),
            'users_by_role' => $this->usersByRoleSeries($branchId, $accessibleBranchIds),
            'permissions_by_group' => $this->permissionsByGroupSeries(),
            'user_status' => $this->userStatusSeries($branchId, $accessibleBranchIds),
        ];
    }

    /**
     * @param  list<int>|null  $accessibleBranchIds
     * @return array{
     *     todays_sales: float,
     *     gross_profit: float,
     *     average_transaction_value: float,
     *     low_stock_alerts: int,
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
            'low_stock_alerts' => 0,
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
     * @return array<string, mixed>
     */
    public function superAdminOverview(?int $branchId = null, ?array $accessibleBranchIds = null): array
    {
        $branchQuery = Branch::query();
        if ($branchId !== null) {
            $branchQuery->whereKey($branchId);
        } elseif ($accessibleBranchIds !== null) {
            $branchQuery->whereIn('id', $accessibleBranchIds);
        }

        $branchesTotal = (int) (clone $branchQuery)->toBase()->count('*');
        $branchesActive = (int) (clone $branchQuery)->where('is_active', true)->toBase()->count('*');

        $inventoryQuery = Inventory::query()
            ->when($branchId !== null || $accessibleBranchIds !== null, function (Builder $query) use ($branchId, $accessibleBranchIds): void {
                $query->whereHas('warehouse', function (Builder $warehouse) use ($branchId, $accessibleBranchIds): void {
                    if ($branchId !== null) {
                        $warehouse->where('branch_id', $branchId);
                    } elseif ($accessibleBranchIds !== null) {
                        $warehouse->whereIn('branch_id', $accessibleBranchIds);
                    }
                });
            });

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

        $transferQuery = StockTransfer::query()
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

        $warehouseQuery = Warehouse::query();
        if ($branchId !== null) {
            $warehouseQuery->where('branch_id', $branchId);
        } elseif ($accessibleBranchIds !== null) {
            $warehouseQuery->whereIn('branch_id', $accessibleBranchIds);
        }

        $movementQuery = StockMovement::query()
            ->when($branchId !== null || $accessibleBranchIds !== null, function (Builder $query) use ($branchId, $accessibleBranchIds): void {
                $query->whereHas('warehouse', function (Builder $warehouse) use ($branchId, $accessibleBranchIds): void {
                    if ($branchId !== null) {
                        $warehouse->where('branch_id', $branchId);
                    } else {
                        $warehouse->whereIn('branch_id', $accessibleBranchIds ?? []);
                    }
                });
            });

        return [
            'branches_active' => $branchesActive,
            'branches_total' => $branchesTotal,
            'warehouses' => (int) (clone $warehouseQuery)->toBase()->count('*'),
            'products_active' => (int) Product::query()->where('is_active', true)->toBase()->count('*'),
            'product_variants' => (int) ProductVariant::query()->toBase()->count('*'),
            'categories' => (int) Category::query()->toBase()->count('*'),
            'brands' => (int) Brand::query()->toBase()->count('*'),
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
            'admin_logins_24h' => (int) AuditLog::query()
                ->where('event', 'login')
                ->where('created_at', '>=', now()->subDay())
                ->toBase()
                ->count('*'),
            'stock_movement_trend' => $this->stockMovementTrendSeries($branchId, $accessibleBranchIds),
            'branches_preview' => $this->branchesPreview($branchId, $accessibleBranchIds),
        ];
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

        $query = StockMovement::query()->where('created_at', '>=', $start);

        if ($branchId !== null || $accessibleBranchIds !== null) {
            $query->whereHas('warehouse', function (Builder $warehouse) use ($branchId, $accessibleBranchIds): void {
                if ($branchId !== null) {
                    $warehouse->where('branch_id', $branchId);
                } else {
                    $warehouse->whereIn('branch_id', $accessibleBranchIds ?? []);
                }
            });
        }

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

    /**
     * @param  list<int>|null  $accessibleBranchIds
     * @return list<array{label: string, count: int}>
     */
    private function userGrowthSeries(
        ?int $branchId = null,
        ?array $accessibleBranchIds = null,
        int $days = 7,
    ): array {
        $start = now()->subDays($days - 1)->startOfDay();

        $counts = $this->scopeUsers(User::query(), $branchId, $accessibleBranchIds)
            ->where('created_at', '>=', $start)
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
     * @return list<array{role: string, count: int}>
     */
    private function usersByRoleSeries(?int $branchId = null, ?array $accessibleBranchIds = null): array
    {
        return Role::query()
            ->where('guard_name', 'web')
            ->withCount(['users' => function (Builder $query) use ($branchId, $accessibleBranchIds): void {
                $this->scopeUsers($query, $branchId, $accessibleBranchIds);
            }])
            ->orderByDesc('users_count')
            ->get()
            ->map(fn (Role $role): array => [
                'role' => $role->name,
                'count' => (int) $role->users_count,
            ])
            ->all();
    }

    /**
     * @return list<array{group: string, count: int}>
     */
    private function permissionsByGroupSeries(): array
    {
        return Permission::query()
            ->select([
                DB::raw("COALESCE(NULLIF(`group`, ''), 'General') as permission_group"),
                DB::raw('COUNT(*) as total'),
            ])
            ->groupBy('permission_group')
            ->orderBy('permission_group')
            ->get()
            ->map(fn (object $row): array => [
                'group' => (string) $row->permission_group,
                'count' => (int) $row->total,
            ])
            ->all();
    }

    /**
     * @param  list<int>|null  $accessibleBranchIds
     * @return list<array{status: string, count: int}>
     */
    private function userStatusSeries(?int $branchId = null, ?array $accessibleBranchIds = null): array
    {
        $base = $this->scopeUsers(User::query(), $branchId, $accessibleBranchIds);
        $active = (int) (clone $base)->where('is_active', true)->toBase()->count('*');
        $inactive = (int) (clone $base)->where('is_active', false)->toBase()->count('*');

        return [
            ['status' => 'Active', 'count' => $active],
            ['status' => 'Inactive', 'count' => $inactive],
        ];
    }

    /**
     * @param  Builder<User>  $query
     * @param  list<int>|null  $accessibleBranchIds
     * @return Builder<User>
     */
    private function scopeUsers(Builder $query, ?int $branchId, ?array $accessibleBranchIds): Builder
    {
        if ($branchId !== null) {
            return $query->whereHas(
                'branches',
                fn (Builder $branch) => $branch->where('branches.id', $branchId),
            );
        }

        if ($accessibleBranchIds !== null) {
            return $query->whereHas(
                'branches',
                fn (Builder $branch) => $branch->whereIn('branches.id', $accessibleBranchIds),
            );
        }

        return $query;
    }

    /**
     * @param  class-string<Model>  $modelClass
     */
    private function modelCount(string $modelClass): int
    {
        /** @var Model $model */
        $model = new $modelClass;

        return (int) $model->newQuery()->toBase()->count('*');
    }
}
