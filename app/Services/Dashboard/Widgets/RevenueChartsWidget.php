<?php

declare(strict_types=1);

namespace App\Services\Dashboard\Widgets;

use App\Models\User;
use App\Services\DashboardService;

final class RevenueChartsWidget extends AbstractDashboardWidget
{
    public function __construct(
        private readonly DashboardService $dashboard,
    ) {}

    public function id(): string
    {
        return 'revenue_charts';
    }

    public function module(): string
    {
        return 'sales';
    }

    public function titleKey(): string
    {
        return 'revenue';
    }

    public function permissions(): array
    {
        return ['dashboard.sales.view'];
    }

    public function sortOrder(): int
    {
        return 15;
    }

    public function isVisible(User $user): bool
    {
        return $user->can('dashboard.sales.view') && $user->can('dashboard.view-profit');
    }

    public function data(User $user, ?int $branchId, ?array $accessibleBranchIds): ?array
    {
        return $this->dashboard->revenueCharts($branchId, $accessibleBranchIds);
    }
}
