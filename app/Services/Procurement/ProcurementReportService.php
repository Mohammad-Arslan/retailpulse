<?php

declare(strict_types=1);

namespace App\Services\Procurement;

use App\Enums\PoMatchStatus;
use App\Enums\PurchaseOrderStatus;
use App\Models\GoodsReceivingNote;
use App\Models\PoMatchResult;
use App\Models\PurchaseOrder;
use App\Models\PurchaseReturn;
use App\Models\Supplier;
use App\Models\SupplierInvoice;
use Illuminate\Support\Collection;

final class ProcurementReportService
{
    public function __construct(
        private readonly SupplierLedgerService $ledger,
    ) {}

    /**
     * @return Collection<int, PurchaseOrder>
     */
    public function openPurchaseOrders(?int $branchId = null): Collection
    {
        return PurchaseOrder::query()
            ->with(['supplier', 'items'])
            ->when($branchId, fn ($q) => $q->where('branch_id', $branchId))
            ->whereIn('status', [
                PurchaseOrderStatus::Submitted->value,
                PurchaseOrderStatus::Approved->value,
            ])
            ->orderByDesc('created_at')
            ->get();
    }

    /**
     * @return Collection<int, PurchaseOrder>
     */
    public function pendingApprovals(?int $branchId = null): Collection
    {
        return PurchaseOrder::query()
            ->with('supplier')
            ->when($branchId, fn ($q) => $q->where('branch_id', $branchId))
            ->where('status', PurchaseOrderStatus::Submitted->value)
            ->orderBy('submitted_at')
            ->get();
    }

    /**
     * @return Collection<int, GoodsReceivingNote>
     */
    public function grnReport(?int $branchId = null, ?string $from = null, ?string $to = null): Collection
    {
        return GoodsReceivingNote::query()
            ->with(['supplier', 'purchaseOrder', 'warehouse'])
            ->when($branchId, fn ($q) => $q->where('branch_id', $branchId))
            ->when($from, fn ($q) => $q->whereDate('received_at', '>=', $from))
            ->when($to, fn ($q) => $q->whereDate('received_at', '<=', $to))
            ->where('status', 'posted')
            ->orderByDesc('received_at')
            ->get();
    }

    /**
     * @return Collection<int, SupplierInvoice>
     */
    public function invoiceReport(?int $branchId = null): Collection
    {
        return SupplierInvoice::query()
            ->with(['supplier', 'matchResult'])
            ->when($branchId, fn ($q) => $q->where('branch_id', $branchId))
            ->orderByDesc('invoice_date')
            ->get();
    }

    /**
     * @return Collection<int, Supplier>
     */
    public function supplierBalances(?int $branchId = null): Collection
    {
        return Supplier::query()
            ->where('is_active', true)
            ->orderBy('name')
            ->get(['id', 'code', 'name', 'balance', 'currency_code', 'on_time_delivery_rate', 'quality_rejection_rate'])
            ->map(function (Supplier $supplier) use ($branchId) {
                $supplier->setAttribute('balance', $this->ledger->getBalance($supplier->id, $branchId));

                return $supplier;
            })
            ->sortByDesc(fn (Supplier $supplier) => (float) $supplier->balance)
            ->values();
    }

    /**
     * @return Collection<int, PoMatchResult>
     */
    public function matchExceptions(?int $branchId = null): Collection
    {
        return PoMatchResult::query()
            ->with(['supplierInvoice.supplier', 'purchaseOrder'])
            ->whereIn('match_status', [PoMatchStatus::PartiallyMatched->value, PoMatchStatus::Unmatched->value])
            ->whereHas('supplierInvoice', function ($q) use ($branchId) {
                if ($branchId !== null) {
                    $q->where('branch_id', $branchId);
                }
            })
            ->orderByDesc('matched_at')
            ->get();
    }

    /**
     * @return Collection<int, PurchaseReturn>
     */
    public function purchaseReturnsReport(?int $branchId = null): Collection
    {
        return PurchaseReturn::query()
            ->with(['supplier'])
            ->when($branchId, fn ($q) => $q->where('branch_id', $branchId))
            ->orderByDesc('created_at')
            ->get();
    }
}
