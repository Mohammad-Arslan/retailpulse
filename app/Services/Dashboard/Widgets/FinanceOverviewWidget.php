<?php

declare(strict_types=1);

namespace App\Services\Dashboard\Widgets;

use App\Models\User;
use App\Services\Dashboard\FinanceDashboardService;

final class FinanceOverviewWidget extends AbstractDashboardWidget
{
    public function __construct(
        private readonly FinanceDashboardService $finance,
    ) {}

    public function id(): string
    {
        return 'finance_overview';
    }

    public function module(): string
    {
        return 'finance';
    }

    public function titleKey(): string
    {
        return 'finance';
    }

    public function permissions(): array
    {
        return ['dashboard.finance.view'];
    }

    public function sortOrder(): int
    {
        return 40;
    }

    public function data(User $user, ?int $branchId, ?array $accessibleBranchIds): ?array
    {
        $kpis = $this->finance->kpis($branchId);

        return [
            ...$kpis,
            'unposted_href' => route('admin.accounting.reports.unposted-journals'),
            'reconciliation_href' => $user->can('accounting.reconcile-bank')
                ? route('admin.accounting.reconciliation.index')
                : null,
            'ar_aging_href' => $user->can('accounting.view-reports') || $user->can('accounting.view')
                ? route('admin.accounting.reports.ar-aging')
                : null,
            'ap_aging_href' => $user->can('accounting.view-reports') || $user->can('accounting.view')
                ? route('admin.accounting.reports.ap-aging')
                : null,
        ];
    }
}
