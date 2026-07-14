<?php

declare(strict_types=1);

namespace App\Services\Dashboard\Widgets;

use App\Models\User;
use App\Services\DashboardService;

final class OperationsOverviewWidget extends AbstractDashboardWidget
{
    public function __construct(
        private readonly DashboardService $dashboard,
    ) {}

    public function id(): string
    {
        return 'operations_overview';
    }

    public function module(): string
    {
        return 'operations';
    }

    public function titleKey(): string
    {
        return 'operations';
    }

    public function permissions(): array
    {
        return ['dashboard.operations.view'];
    }

    public function sortOrder(): int
    {
        return 50;
    }

    public function data(User $user, ?int $branchId, ?array $accessibleBranchIds): ?array
    {
        $overview = $this->dashboard->operationsOverview($branchId, $accessibleBranchIds);

        return [
            ...$overview,
            'branches_href' => $user->can('branches.view') ? route('admin.branches.index') : null,
            'products_href' => $user->can('products.view') ? route('admin.products.index') : null,
            'warehouses_href' => $user->can('warehouses.view') ? route('admin.warehouses.index') : null,
        ];
    }
}
