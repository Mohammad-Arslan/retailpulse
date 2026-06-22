<?php

declare(strict_types=1);

namespace App\Services\Procurement;

use App\Enums\PurchaseOrderStatus;
use App\Enums\SupplierInvoiceStatus;
use App\Models\PurchaseOrder;
use App\Models\PurchaseOrderItem;
use App\Models\PurchaseReturn;
use App\Models\Supplier;
use App\Models\SupplierInvoice;

final class ProcurementDashboardService
{
    public function __construct(
        private readonly SupplierLedgerService $ledger,
    ) {}

    /**
     * @return array<string, mixed>
     */
    public function kpis(?int $branchId = null): array
    {
        $poQuery = PurchaseOrder::query()->when($branchId, fn ($q) => $q->where('branch_id', $branchId));

        $openPos = (int) (clone $poQuery)
            ->whereIn('status', [PurchaseOrderStatus::Approved->value, PurchaseOrderStatus::Submitted->value])
            ->count();

        $pendingApprovals = (int) (clone $poQuery)
            ->where('status', PurchaseOrderStatus::Submitted->value)
            ->count();

        $pendingReceipts = (int) PurchaseOrderItem::query()
            ->whereHas('purchaseOrder', function ($q) use ($branchId) {
                $q->where('status', PurchaseOrderStatus::Approved->value);
                if ($branchId !== null) {
                    $q->where('branch_id', $branchId);
                }
            })
            ->whereColumn('qty_received', '<', 'qty_ordered')
            ->count();

        $pendingInvoices = (int) SupplierInvoice::query()
            ->when($branchId, fn ($q) => $q->where('branch_id', $branchId))
            ->whereIn('status', [SupplierInvoiceStatus::Draft->value, SupplierInvoiceStatus::Matched->value])
            ->count();

        $outstandingPayables = (float) Supplier::query()
            ->where('is_active', true)
            ->get(['id'])
            ->sum(fn (Supplier $supplier) => $this->ledger->getBalance($supplier->id, $branchId));

        $monthlyPurchases = (float) PurchaseOrder::query()
            ->when($branchId, fn ($q) => $q->where('branch_id', $branchId))
            ->whereHas('grns', fn ($q) => $q->where('status', 'posted')->where('received_at', '>=', now()->startOfMonth()))
            ->sum('total');

        $openReturns = (int) PurchaseReturn::query()
            ->when($branchId, fn ($q) => $q->where('branch_id', $branchId))
            ->whereNotIn('status', ['closed'])
            ->count();

        $topSuppliers = Supplier::query()
            ->where('is_active', true)
            ->get(['id', 'name', 'code', 'on_time_delivery_rate'])
            ->map(function (Supplier $supplier) use ($branchId) {
                $balance = $this->ledger->getBalance($supplier->id, $branchId);

                return [
                    'id' => $supplier->id,
                    'name' => $supplier->name,
                    'code' => $supplier->code,
                    'balance' => number_format($balance, 2, '.', ''),
                    'on_time_delivery_rate' => $supplier->on_time_delivery_rate,
                ];
            })
            ->sortByDesc(fn (array $row) => (float) $row['balance'])
            ->take(5)
            ->values()
            ->all();

        return [
            'open_pos' => $openPos,
            'pending_approvals' => $pendingApprovals,
            'pending_receipts' => $pendingReceipts,
            'pending_invoices' => $pendingInvoices,
            'outstanding_payables' => round($outstandingPayables, 2),
            'monthly_purchases' => round($monthlyPurchases, 2),
            'open_returns' => $openReturns,
            'top_suppliers' => $topSuppliers,
        ];
    }
}
