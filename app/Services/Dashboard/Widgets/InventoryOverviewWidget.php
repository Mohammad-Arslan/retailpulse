<?php

declare(strict_types=1);

namespace App\Services\Dashboard\Widgets;

use App\Models\User;
use App\Services\DashboardService;

final class InventoryOverviewWidget extends AbstractDashboardWidget
{
    public function __construct(
        private readonly DashboardService $dashboard,
    ) {}

    public function id(): string
    {
        return 'inventory_overview';
    }

    public function module(): string
    {
        return 'inventory';
    }

    public function titleKey(): string
    {
        return 'inventory';
    }

    public function permissions(): array
    {
        return ['dashboard.inventory.view'];
    }

    public function sortOrder(): int
    {
        return 20;
    }

    public function data(User $user, ?int $branchId, ?array $accessibleBranchIds): ?array
    {
        $health = $this->dashboard->inventoryHealth($branchId, $accessibleBranchIds);

        return [
            ...$health,
            'inventory_index_href' => route('admin.inventory.index'),
            'transfers_index_href' => route('admin.stock-transfers.index'),
        ];
    }
}
