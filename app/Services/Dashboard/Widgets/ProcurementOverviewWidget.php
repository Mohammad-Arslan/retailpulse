<?php

declare(strict_types=1);

namespace App\Services\Dashboard\Widgets;

use App\Models\User;
use App\Services\Procurement\ProcurementDashboardService;

final class ProcurementOverviewWidget extends AbstractDashboardWidget
{
    public function __construct(
        private readonly ProcurementDashboardService $procurement,
    ) {}

    public function id(): string
    {
        return 'procurement_overview';
    }

    public function module(): string
    {
        return 'procurement';
    }

    public function titleKey(): string
    {
        return 'procurement';
    }

    public function permissions(): array
    {
        return ['dashboard.procurement.view'];
    }

    public function sortOrder(): int
    {
        return 30;
    }

    public function data(User $user, ?int $branchId, ?array $accessibleBranchIds): ?array
    {
        $kpis = $this->procurement->kpis($branchId);

        return [
            ...$kpis,
            'reports_href' => route('admin.procurement.reports'),
        ];
    }
}
