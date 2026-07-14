<?php

declare(strict_types=1);

namespace App\Services\Dashboard\Widgets;

use App\Models\User;

/**
 * Permission-filtered operational shortcuts — never IAM admin links.
 */
final class QuickActionsWidget extends AbstractDashboardWidget
{
    public function id(): string
    {
        return 'quick_actions';
    }

    public function module(): string
    {
        return 'operations';
    }

    public function titleKey(): string
    {
        return 'quickActions';
    }

    public function permissions(): array
    {
        return [
            'dashboard.view',
            'admin.dashboard.view',
            'pos.access',
            'sales.view',
            'inventory.view',
            'procurement.view',
            'accounting.view',
            'accounting.view-reports',
        ];
    }

    public function sortOrder(): int
    {
        return 100;
    }

    public function data(User $user, ?int $branchId, ?array $accessibleBranchIds): ?array
    {
        $actions = [];

        $catalog = [
            [
                'id' => 'pos',
                'label_key' => 'pos',
                'desc_key' => 'posDesc',
                'permission' => 'pos.access',
                'href' => 'admin.pos.index',
            ],
            [
                'id' => 'sales',
                'label_key' => 'sales',
                'desc_key' => 'salesDesc',
                'permission' => 'sales.view',
                'href' => 'admin.sales.index',
            ],
            [
                'id' => 'inventory',
                'label_key' => 'inventory',
                'desc_key' => 'inventoryDesc',
                'permission' => 'inventory.view',
                'href' => 'admin.inventory.index',
            ],
            [
                'id' => 'transfers',
                'label_key' => 'transfers',
                'desc_key' => 'transfersDesc',
                'permission' => 'inventory.transfer',
                'href' => 'admin.stock-transfers.index',
            ],
            [
                'id' => 'purchase_orders',
                'label_key' => 'purchaseOrders',
                'desc_key' => 'purchaseOrdersDesc',
                'permission' => 'procurement.view',
                'href' => 'admin.purchase-orders.index',
            ],
            [
                'id' => 'accounting_reports',
                'label_key' => 'accountingReports',
                'desc_key' => 'accountingReportsDesc',
                'permission' => 'accounting.view-reports',
                'alt_permission' => 'accounting.view',
                'href' => 'admin.accounting.reports.index',
            ],
            [
                'id' => 'products',
                'label_key' => 'products',
                'desc_key' => 'productsDesc',
                'permission' => 'products.view',
                'href' => 'admin.products.index',
            ],
            [
                'id' => 'customers',
                'label_key' => 'customers',
                'desc_key' => 'customersDesc',
                'permission' => 'customers.view',
                'href' => 'admin.customers.index',
            ],
        ];

        foreach ($catalog as $action) {
            $allowed = $user->can($action['permission'])
                || (isset($action['alt_permission']) && $user->can($action['alt_permission']));

            if (! $allowed) {
                continue;
            }

            $actions[] = [
                'id' => $action['id'],
                'label_key' => $action['label_key'],
                'desc_key' => $action['desc_key'],
                'href' => route($action['href']),
            ];
        }

        if ($actions === []) {
            return null;
        }

        return ['actions' => $actions];
    }
}
