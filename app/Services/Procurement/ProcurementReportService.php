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
     * @param  array{search?: string|null, supplier_id?: string|null}  $filters
     * @return Collection<int, PurchaseOrder>
     */
    public function openPurchaseOrders(?int $branchId = null, array $filters = []): Collection
    {
        return PurchaseOrder::query()
            ->with(['supplier', 'items'])
            ->when($branchId, fn ($q) => $q->where('branch_id', $branchId))
            ->when($filters['supplier_id'] ?? null, fn ($q, $supplierId) => $q->where('supplier_id', $supplierId))
            ->when($filters['search'] ?? null, function ($q, string $search) {
                $term = '%'.addcslashes($search, '%_\\').'%';
                $q->where(function ($q) use ($term) {
                    $q->where('reference_no', 'like', $term)
                        ->orWhereHas('supplier', fn ($sq) => $sq->where('name', 'like', $term));
                });
            })
            ->whereIn('status', [
                PurchaseOrderStatus::Submitted->value,
                PurchaseOrderStatus::Approved->value,
            ])
            ->orderByDesc('created_at')
            ->get();
    }

    /**
     * @param  array{search?: string|null, supplier_id?: string|null}  $filters
     * @return Collection<int, PurchaseOrder>
     */
    public function pendingApprovals(?int $branchId = null, array $filters = []): Collection
    {
        return PurchaseOrder::query()
            ->with('supplier')
            ->when($branchId, fn ($q) => $q->where('branch_id', $branchId))
            ->when($filters['supplier_id'] ?? null, fn ($q, $supplierId) => $q->where('supplier_id', $supplierId))
            ->when($filters['search'] ?? null, function ($q, string $search) {
                $term = '%'.addcslashes($search, '%_\\').'%';
                $q->where(function ($q) use ($term) {
                    $q->where('reference_no', 'like', $term)
                        ->orWhereHas('supplier', fn ($sq) => $sq->where('name', 'like', $term));
                });
            })
            ->where('status', PurchaseOrderStatus::Submitted->value)
            ->orderBy('submitted_at')
            ->get();
    }

    /**
     * @param  array{search?: string|null, supplier_id?: string|null, from?: string|null, to?: string|null}  $filters
     * @return Collection<int, GoodsReceivingNote>
     */
    public function grnReport(?int $branchId = null, array $filters = []): Collection
    {
        return GoodsReceivingNote::query()
            ->with(['supplier', 'purchaseOrder', 'warehouse'])
            ->when($branchId, fn ($q) => $q->where('branch_id', $branchId))
            ->when($filters['from'] ?? null, fn ($q, string $from) => $q->whereDate('received_at', '>=', $from))
            ->when($filters['to'] ?? null, fn ($q, string $to) => $q->whereDate('received_at', '<=', $to))
            ->when($filters['supplier_id'] ?? null, fn ($q, $supplierId) => $q->where('supplier_id', $supplierId))
            ->when($filters['search'] ?? null, function ($q, string $search) {
                $term = '%'.addcslashes($search, '%_\\').'%';
                $q->where(function ($q) use ($term) {
                    $q->where('reference_no', 'like', $term)
                        ->orWhereHas('supplier', fn ($sq) => $sq->where('name', 'like', $term))
                        ->orWhereHas('purchaseOrder', fn ($pq) => $pq->where('reference_no', 'like', $term));
                });
            })
            ->where('status', 'posted')
            ->orderByDesc('received_at')
            ->get();
    }

    /**
     * @param  array{search?: string|null, status?: string|null, match_status?: string|null, supplier_id?: string|null}  $filters
     * @return Collection<int, SupplierInvoice>
     */
    public function invoiceReport(?int $branchId = null, array $filters = []): Collection
    {
        return SupplierInvoice::query()
            ->with(['supplier', 'matchResult'])
            ->when($branchId, fn ($q) => $q->where('branch_id', $branchId))
            ->when($filters['status'] ?? null, fn ($q, string $status) => $q->where('status', $status))
            ->when($filters['match_status'] ?? null, fn ($q, string $matchStatus) => $q->whereHas(
                'matchResult',
                fn ($mq) => $mq->where('match_status', $matchStatus),
            ))
            ->when($filters['supplier_id'] ?? null, fn ($q, $supplierId) => $q->where('supplier_id', $supplierId))
            ->when($filters['search'] ?? null, function ($q, string $search) {
                $term = '%'.addcslashes($search, '%_\\').'%';
                $q->where(function ($q) use ($term) {
                    $q->where('reference_no', 'like', $term)
                        ->orWhereHas('supplier', fn ($sq) => $sq->where('name', 'like', $term));
                });
            })
            ->orderByDesc('invoice_date')
            ->get();
    }

    /**
     * @param  array{search?: string|null, supplier_id?: string|null}  $filters
     * @return Collection<int, Supplier>
     */
    public function supplierBalances(?int $branchId = null, array $filters = []): Collection
    {
        return Supplier::query()
            ->where('is_active', true)
            ->when($filters['search'] ?? null, function ($q, string $search) {
                $term = '%'.addcslashes($search, '%_\\').'%';
                $q->where(function ($q) use ($term) {
                    $q->where('name', 'like', $term)
                        ->orWhere('code', 'like', $term);
                });
            })
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
     * @param  array{search?: string|null, supplier_id?: string|null}  $filters
     * @return Collection<int, PoMatchResult>
     */
    public function matchExceptions(?int $branchId = null, array $filters = []): Collection
    {
        return PoMatchResult::query()
            ->with(['supplierInvoice.supplier', 'purchaseOrder'])
            ->whereIn('match_status', [PoMatchStatus::PartiallyMatched->value, PoMatchStatus::Unmatched->value])
            ->whereHas('supplierInvoice', function ($q) use ($branchId, $filters) {
                if ($branchId !== null) {
                    $q->where('branch_id', $branchId);
                }
                if ($filters['supplier_id'] ?? null) {
                    $q->where('supplier_id', $filters['supplier_id']);
                }
                if ($filters['search'] ?? null) {
                    $term = '%'.addcslashes($filters['search'], '%_\\').'%';
                    $q->where(function ($q) use ($term) {
                        $q->where('reference_no', 'like', $term)
                            ->orWhereHas('supplier', fn ($sq) => $sq->where('name', 'like', $term));
                    });
                }
            })
            ->orderByDesc('matched_at')
            ->get();
    }

    /**
     * @param  array{search?: string|null, status?: string|null, supplier_id?: string|null}  $filters
     * @return Collection<int, PurchaseReturn>
     */
    public function purchaseReturnsReport(?int $branchId = null, array $filters = []): Collection
    {
        return PurchaseReturn::query()
            ->with(['supplier'])
            ->when($branchId, fn ($q) => $q->where('branch_id', $branchId))
            ->when($filters['status'] ?? null, fn ($q, string $status) => $q->where('status', $status))
            ->when($filters['supplier_id'] ?? null, fn ($q, $supplierId) => $q->where('supplier_id', $supplierId))
            ->when($filters['search'] ?? null, function ($q, string $search) {
                $term = '%'.addcslashes($search, '%_\\').'%';
                $q->where(function ($q) use ($term) {
                    $q->where('reference_no', 'like', $term)
                        ->orWhereHas('supplier', fn ($sq) => $sq->where('name', 'like', $term));
                });
            })
            ->orderByDesc('created_at')
            ->get();
    }
}
