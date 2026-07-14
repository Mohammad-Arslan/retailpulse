<?php

declare(strict_types=1);

namespace App\Services\Dashboard\Widgets;

use App\Models\User;
use App\Services\DashboardService;

final class SalesKpisWidget extends AbstractDashboardWidget
{
    public function __construct(
        private readonly DashboardService $dashboard,
    ) {}

    public function id(): string
    {
        return 'sales_kpis';
    }

    public function module(): string
    {
        return 'sales';
    }

    public function titleKey(): string
    {
        return 'sales';
    }

    public function permissions(): array
    {
        return ['dashboard.sales.view'];
    }

    public function sortOrder(): int
    {
        return 10;
    }

    public function data(User $user, ?int $branchId, ?array $accessibleBranchIds): ?array
    {
        $kpis = $this->dashboard->salesKpis($branchId, $accessibleBranchIds);
        $canViewProfit = $user->can('dashboard.view-profit');

        return [
            'todays_sales' => $kpis['todays_sales'],
            'transaction_count' => $kpis['transaction_count'],
            'average_transaction_value' => $kpis['average_transaction_value'],
            'pending_layaways' => $kpis['pending_approvals'],
            'gross_profit' => $canViewProfit ? $kpis['gross_profit'] : null,
            'can_view_profit' => $canViewProfit,
            'sales_index_href' => route('admin.sales.index'),
        ];
    }
}
