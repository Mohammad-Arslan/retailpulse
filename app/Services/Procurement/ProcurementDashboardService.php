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
            ->sum('balance');

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
            ->orderByDesc('balance')
            ->limit(5)
            ->get(['id', 'name', 'code', 'balance', 'on_time_delivery_rate'])
            ->map(fn (Supplier $s) => [
                'id' => $s->id,
                'name' => $s->name,
                'code' => $s->code,
                'balance' => number_format((float) $s->balance, 2, '.', ''),
                'on_time_delivery_rate' => $s->on_time_delivery_rate,
            ])
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
