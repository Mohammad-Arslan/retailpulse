<?php

declare(strict_types=1);

namespace App\Services;

use App\Enums\ProductType;
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
use App\Models\StockMovement;
use App\Models\StockTransfer;
use App\Models\User;
use App\Models\Warehouse;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\DB;

final class DashboardService
{
    /**
     * @return array{
     *     users: int,
     *     roles: int,
     *     permissions: int,
     *     active_users: int,
     *     inactive_users: int,
     * }
     */
    public function stats(): array
    {
        $users = $this->modelCount(User::class);
        $activeUsers = (int) User::query()->where('is_active', true)->toBase()->count('*');

        return [
            'users' => $users,
            'roles' => $this->modelCount(Role::class),
            'permissions' => $this->modelCount(Permission::class),
            'active_users' => $activeUsers,
            'inactive_users' => max(0, $users - $activeUsers),
        ];
    }

    /**
     * @return array{
     *     user_growth: list<array{label: string, count: int}>,
     *     users_by_role: list<array{role: string, count: int}>,
     *     permissions_by_group: list<array{group: string, count: int}>,
     *     user_status: list<array{status: string, count: int}>,
     * }
     */
    public function charts(): array
    {
        return [
            'user_growth' => $this->userGrowthSeries(),
            'users_by_role' => $this->usersByRoleSeries(),
            'permissions_by_group' => $this->permissionsByGroupSeries(),
            'user_status' => $this->userStatusSeries(),
        ];
    }

    /**
     * Cross-branch operational snapshot for super-admins.
     *
     * @return array{
     *     branches_active: int,
     *     branches_total: int,
     *     warehouses: int,
     *     products_active: int,
     *     product_variants: int,
     *     categories: int,
     *     brands: int,
     *     units_on_hand: int,
     *     low_stock_lines: int,
     *     stock_movements_today: int,
     *     transfers_draft: int,
     *     transfers_in_transit: int,
     *     admin_logins_24h: int,
     *     stock_movement_trend: list<array{label: string, count: int}>,
     *     branches_preview: list<array{id: int, name: string, code: string, warehouses_count: int, users_count: int}>,
     * }
     */
    public function superAdminOverview(): array
    {
        $branchesTotal = (int) Branch::query()->toBase()->count('*');
        $branchesActive = (int) Branch::query()->where('is_active', true)->toBase()->count('*');

        $lowStockLines = (int) Inventory::query()
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

        $transfersDraft = (int) StockTransfer::query()
            ->where('status', StockTransferStatus::Draft)
            ->toBase()
            ->count('*');
        $transfersInTransit = (int) StockTransfer::query()
            ->where('status', StockTransferStatus::Shipped)
            ->toBase()
            ->count('*');

        return [
            'branches_active' => $branchesActive,
            'branches_total' => $branchesTotal,
            'warehouses' => (int) Warehouse::query()->toBase()->count('*'),
            'products_active' => (int) Product::query()->where('is_active', true)->toBase()->count('*'),
            'product_variants' => (int) ProductVariant::query()->toBase()->count('*'),
            'categories' => (int) Category::query()->toBase()->count('*'),
            'brands' => (int) Brand::query()->toBase()->count('*'),
            'units_on_hand' => (int) Inventory::query()->toBase()->sum('quantity_on_hand'),
            'low_stock_lines' => $lowStockLines,
            'stock_movements_today' => (int) StockMovement::query()
                ->whereDate('created_at', '=', today(), 'and')
                ->toBase()
                ->count('*'),
            'transfers_draft' => $transfersDraft,
            'transfers_in_transit' => $transfersInTransit,
            'admin_logins_24h' => (int) AuditLog::query()
                ->where('event', 'login')
                ->where('created_at', '>=', now()->subDay())
                ->toBase()
                ->count('*'),
            'stock_movement_trend' => $this->stockMovementTrendSeries(),
            'branches_preview' => $this->branchesPreview(),
        ];
    }

    /**
     * @return list<array{label: string, count: int}>
     */
    private function stockMovementTrendSeries(int $days = 7): array
    {
        $start = now()->subDays($days - 1)->startOfDay();

        $counts = StockMovement::query()
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
     * @return list<array{id: int, name: string, code: string, warehouses_count: int, users_count: int}>
     */
    private function branchesPreview(int $limit = 6): array
    {
        return Branch::query()
            ->where('is_active', true)
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
     * @return list<array{label: string, count: int}>
     */
    private function userGrowthSeries(int $days = 7): array
    {
        $start = now()->subDays($days - 1)->startOfDay();

        $counts = User::query()
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
     * @return list<array{role: string, count: int}>
     */
    private function usersByRoleSeries(): array
    {
        return Role::query()
            ->where('guard_name', 'web')
            ->withCount('users')
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
     * @return list<array{status: string, count: int}>
     */
    private function userStatusSeries(): array
    {
        $active = (int) User::query()->where('is_active', true)->toBase()->count('*');
        $inactive = (int) User::query()->where('is_active', false)->toBase()->count('*');

        return [
            ['status' => 'Active', 'count' => $active],
            ['status' => 'Inactive', 'count' => $inactive],
        ];
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
