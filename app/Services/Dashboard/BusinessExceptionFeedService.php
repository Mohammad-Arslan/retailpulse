<?php

declare(strict_types=1);

namespace App\Services\Dashboard;

use App\Enums\StockTransferStatus;
use App\Models\StockTransfer;
use App\Models\User;
use App\Services\DashboardService;
use App\Services\Procurement\ProcurementAlertService;
use App\Services\Procurement\ProcurementDashboardService;
use Illuminate\Support\Carbon;

/**
 * Aggregates actionable business exceptions across modules.
 * Technical audit events (login, role changes) are intentionally excluded.
 */
final class BusinessExceptionFeedService
{
    public function __construct(
        private readonly ProcurementAlertService $procurementAlerts,
        private readonly ProcurementDashboardService $procurement,
        private readonly DashboardService $dashboard,
        private readonly FinanceDashboardService $finance,
    ) {}

    /**
     * @param  list<int>|null  $accessibleBranchIds
     * @return list<array{
     *     id: string,
     *     type: string,
     *     module: string,
     *     title: string,
     *     message: string,
     *     severity: string,
     *     href: string|null,
     *     created_at: string|null
     * }>
     */
    public function forUser(User $user, ?int $branchId, ?array $accessibleBranchIds, int $limit = 15): array
    {
        $items = [];

        if ($user->can('dashboard.procurement.view') || $user->can('procurement.view')) {
            foreach ($this->procurementAlerts->recentUnreadForUser($user, $limit) as $alert) {
                $href = null;
                if (is_string($alert->link_route) && $alert->link_route !== '') {
                    try {
                        $href = route($alert->link_route, $alert->link_params ?? []);
                    } catch (\Throwable) {
                        $href = null;
                    }
                }

                $items[] = [
                    'id' => 'procurement-alert-'.$alert->id,
                    'type' => (string) $alert->type,
                    'module' => 'procurement',
                    'title' => (string) $alert->title,
                    'message' => (string) $alert->message,
                    'severity' => 'warning',
                    'href' => $href,
                    'created_at' => $alert->created_at?->toIso8601String(),
                ];
            }

            $kpis = $this->procurement->kpis($branchId);
            if ((int) ($kpis['pending_approvals'] ?? 0) > 0) {
                $items[] = [
                    'id' => 'po-pending-approvals',
                    'type' => 'po_pending_approval',
                    'module' => 'procurement',
                    'title' => 'Purchase Orders Awaiting Approval',
                    'message' => sprintf('%d purchase order(s) need approval.', (int) $kpis['pending_approvals']),
                    'severity' => 'warning',
                    'href' => route('admin.procurement.reports', ['tab' => 'pending-approvals']),
                    'created_at' => now()->toIso8601String(),
                ];
            }
            if ((int) ($kpis['pending_receipts'] ?? 0) > 0) {
                $items[] = [
                    'id' => 'grn-pending',
                    'type' => 'grn_pending',
                    'module' => 'procurement',
                    'title' => 'Goods Receipts Pending',
                    'message' => sprintf('%d open order(s) awaiting receipt.', (int) $kpis['pending_receipts']),
                    'severity' => 'info',
                    'href' => route('admin.procurement.reports', ['tab' => 'grns']),
                    'created_at' => now()->toIso8601String(),
                ];
            }
        }

        if ($user->can('dashboard.inventory.view') || $user->can('inventory.view')) {
            $inventory = $this->dashboard->inventoryHealth($branchId, $accessibleBranchIds);
            if ((int) ($inventory['low_stock_lines'] ?? 0) > 0) {
                $items[] = [
                    'id' => 'low-stock',
                    'type' => 'low_stock',
                    'module' => 'inventory',
                    'title' => 'Low Stock Detected',
                    'message' => sprintf(
                        '%d line(s) at or below reorder point%s.',
                        (int) $inventory['low_stock_lines'],
                        (int) ($inventory['critical_low_stock_lines'] ?? 0) > 0
                            ? sprintf(' (%d critical)', (int) $inventory['critical_low_stock_lines'])
                            : '',
                    ),
                    'severity' => (int) ($inventory['critical_low_stock_lines'] ?? 0) > 0 ? 'critical' : 'warning',
                    'href' => route('admin.inventory.index'),
                    'created_at' => now()->toIso8601String(),
                ];
            }

            $overdueTransfers = $this->overdueTransferCount($branchId, $accessibleBranchIds);
            if ($overdueTransfers > 0) {
                $items[] = [
                    'id' => 'transfer-overdue',
                    'type' => 'transfer_overdue',
                    'module' => 'inventory',
                    'title' => 'Transfers Overdue',
                    'message' => sprintf('%d in-transit transfer(s) older than 3 days.', $overdueTransfers),
                    'severity' => 'warning',
                    'href' => route('admin.stock-transfers.index'),
                    'created_at' => now()->toIso8601String(),
                ];
            }
        }

        if ($user->can('dashboard.finance.view') || $user->can('accounting.view')) {
            $finance = $this->finance->kpis($branchId);
            if ((int) $finance['unposted_journals'] > 0) {
                $items[] = [
                    'id' => 'journals-unposted',
                    'type' => 'journal_unposted',
                    'module' => 'finance',
                    'title' => 'Journals Awaiting Posting',
                    'message' => sprintf('%d journal(s) still draft, pending approval, or approved.', (int) $finance['unposted_journals']),
                    'severity' => 'warning',
                    'href' => route('admin.accounting.reports.unposted-journals'),
                    'created_at' => now()->toIso8601String(),
                ];
            }
            if ((int) $finance['bank_unmatched'] > 0 && $user->can('accounting.reconcile-bank')) {
                $items[] = [
                    'id' => 'bank-unmatched',
                    'type' => 'bank_unmatched',
                    'module' => 'finance',
                    'title' => 'Bank Lines Unmatched',
                    'message' => sprintf('%d statement line(s) need matching.', (int) $finance['bank_unmatched']),
                    'severity' => 'info',
                    'href' => route('admin.accounting.reconciliation.index'),
                    'created_at' => now()->toIso8601String(),
                ];
            }
            if ((float) $finance['ap_aging_total'] > 0 && $user->can('procurement.process-payments')) {
                $items[] = [
                    'id' => 'ap-outstanding',
                    'type' => 'ap_aging',
                    'module' => 'finance',
                    'title' => 'Outstanding Supplier Payables',
                    'message' => sprintf('AP aging total is %s.', number_format((float) $finance['ap_aging_total'], 2)),
                    'severity' => 'info',
                    'href' => route('admin.accounting.reports.ap-aging'),
                    'created_at' => now()->toIso8601String(),
                ];
            }
        }

        if ($user->can('dashboard.sales.view') || $user->can('sales.view')) {
            $sales = $this->dashboard->salesKpis($branchId, $accessibleBranchIds);
            if ((int) ($sales['pending_approvals'] ?? 0) > 0) {
                $items[] = [
                    'id' => 'layaway-open',
                    'type' => 'layaway_open',
                    'module' => 'sales',
                    'title' => 'Open Layaways',
                    'message' => sprintf('%d partially paid sale(s) still open.', (int) $sales['pending_approvals']),
                    'severity' => 'info',
                    'href' => route('admin.sales.index'),
                    'created_at' => now()->toIso8601String(),
                ];
            }
        }

        usort($items, function (array $a, array $b): int {
            $severityOrder = ['critical' => 0, 'warning' => 1, 'info' => 2];

            return ($severityOrder[$a['severity']] ?? 9) <=> ($severityOrder[$b['severity']] ?? 9);
        });

        return array_slice($items, 0, $limit);
    }

    /**
     * @param  list<int>|null  $accessibleBranchIds
     */
    private function overdueTransferCount(?int $branchId, ?array $accessibleBranchIds): int
    {
        $cutoff = Carbon::now()->subDays(3);

        $query = StockTransfer::query()
            ->where('status', StockTransferStatus::Shipped)
            ->where('updated_at', '<=', $cutoff);

        if ($branchId !== null || $accessibleBranchIds !== null) {
            $query->where(function ($q) use ($branchId, $accessibleBranchIds): void {
                $q->whereHas('fromWarehouse', function ($w) use ($branchId, $accessibleBranchIds): void {
                    if ($branchId !== null) {
                        $w->where('branch_id', $branchId);
                    } else {
                        $w->whereIn('branch_id', $accessibleBranchIds ?? []);
                    }
                })->orWhereHas('toWarehouse', function ($w) use ($branchId, $accessibleBranchIds): void {
                    if ($branchId !== null) {
                        $w->where('branch_id', $branchId);
                    } else {
                        $w->whereIn('branch_id', $accessibleBranchIds ?? []);
                    }
                });
            });
        }

        return (int) $query->toBase()->count('*');
    }
}
