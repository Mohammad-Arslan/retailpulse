<?php

declare(strict_types=1);

namespace App\Services\Procurement;

use App\DTOs\Procurement\CreatePurchaseOrderData;
use App\DTOs\Procurement\CreatePurchaseRequestData;
use App\DTOs\Procurement\PurchaseOrderLineData;
use App\DTOs\Procurement\PurchaseRequestLineData;
use App\Enums\ProcurementDocumentType;
use App\Enums\PurchaseRequestStatus;
use App\Events\Procurement\PurchaseRequestApproved;
use App\Events\Procurement\PurchaseRequestConverted;
use App\Events\Procurement\PurchaseRequestRejected;
use App\Events\Procurement\PurchaseRequestSubmitted;
use App\Models\PurchaseOrder;
use App\Models\PurchaseRequest;
use App\Models\User;
use App\Repositories\Contracts\PurchaseRequestRepositoryInterface;
use App\Services\Procurement\Approval\PrApprovalStrategyFactory;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

final class PurchaseRequestService
{
    public function __construct(
        private readonly PurchaseRequestRepositoryInterface $requests,
        private readonly ProcurementDocumentNumberService $documentNumbers,
        private readonly ProcurementConfigService $config,
        private readonly PurchaseOrderService $purchaseOrders,
        private readonly PrApprovalStrategyFactory $approvalFactory,
    ) {}

    public function create(CreatePurchaseRequestData $data): PurchaseRequest
    {
        if ($data->lines === []) {
            throw ValidationException::withMessages(['lines' => __('At least one line item is required.')]);
        }

        return DB::transaction(function () use ($data) {
            $totals = $this->calculateTotals($data->lines, $data->exchangeRate);

            $request = $this->requests->create([
                'branch_id' => $data->branchId,
                'warehouse_id' => $data->warehouseId,
                'reference_no' => $this->documentNumbers->next($data->branchId, ProcurementDocumentType::PurchaseRequest),
                'status' => PurchaseRequestStatus::Draft,
                'currency_code' => $data->currencyCode,
                'exchange_rate' => $data->exchangeRate,
                'subtotal' => $totals['subtotal'],
                'tax_total' => $totals['tax_total'],
                'total' => $totals['total'],
                'functional_total' => $totals['functional_total'],
                'needed_by' => $data->neededBy,
                'notes' => $data->notes,
                'created_by' => $data->userId,
                'updated_by' => $data->userId,
            ]);

            foreach ($data->lines as $index => $line) {
                $this->createLine($request, $line, $index);
            }

            return $this->requests->findByIdWithRelations($request->id) ?? $request;
        });
    }

    public function submit(PurchaseRequest $request, int $userId): PurchaseRequest
    {
        if (! $request->status->canSubmit()) {
            throw ValidationException::withMessages(['status' => __('Only draft purchase requests can be submitted.')]);
        }

        $updated = $this->requests->update($request, [
            'status' => PurchaseRequestStatus::Submitted,
            'submitted_at' => now(),
            'updated_by' => $userId,
        ]);

        event(new PurchaseRequestSubmitted($updated));

        return $this->requests->findByIdWithRelations($updated->id) ?? $updated;
    }

    public function approve(PurchaseRequest $request, User $approver, ?string $managerPin = null): PurchaseRequest
    {
        if (! $request->status->canApprove()) {
            throw ValidationException::withMessages(['status' => __('Only submitted purchase requests can be approved.')]);
        }

        if ($this->config->requiresPrApproval((float) $request->total, $request->branch_id)) {
            $this->approvalFactory->make()->approve($request, $approver, $managerPin);
        }

        $updated = DB::transaction(function () use ($request, $approver) {
            $result = $this->requests->update($request, [
                'status' => PurchaseRequestStatus::Approved,
                'approved_by' => $approver->id,
                'approved_at' => now(),
                'updated_by' => $approver->id,
            ]);

            event(new PurchaseRequestApproved($result));

            return $result;
        });

        return $this->requests->findByIdWithRelations($updated->id) ?? $updated;
    }

    public function reject(PurchaseRequest $request, int $userId, string $reason): PurchaseRequest
    {
        if (! $request->status->canReject()) {
            throw ValidationException::withMessages(['status' => __('Only submitted purchase requests can be rejected.')]);
        }

        $updated = $this->requests->update($request, [
            'status' => PurchaseRequestStatus::Rejected,
            'rejected_by' => $userId,
            'rejected_at' => now(),
            'rejection_reason' => $reason,
            'updated_by' => $userId,
        ]);

        event(new PurchaseRequestRejected($updated));

        return $this->requests->findByIdWithRelations($updated->id) ?? $updated;
    }

    public function cancel(PurchaseRequest $request, int $userId): PurchaseRequest
    {
        if (! $request->status->canCancel()) {
            throw ValidationException::withMessages(['status' => __('This purchase request cannot be cancelled.')]);
        }

        return $this->requests->update($request, [
            'status' => PurchaseRequestStatus::Cancelled,
            'cancelled_at' => now(),
            'updated_by' => $userId,
        ]);
    }

    public function convertToPurchaseOrder(PurchaseRequest $request, int $supplierId, int $userId): PurchaseOrder
    {
        if (! $request->status->canConvert()) {
            throw ValidationException::withMessages(['status' => __('Only approved purchase requests can be converted to a purchase order.')]);
        }

        $request->loadMissing('items');

        if ($request->items->isEmpty()) {
            throw ValidationException::withMessages(['lines' => __('Purchase request has no lines to convert.')]);
        }

        return DB::transaction(function () use ($request, $supplierId, $userId) {
            $lines = [];

            foreach ($request->items as $item) {
                $lines[] = new PurchaseOrderLineData(
                    variantId: (int) $item->product_variant_id,
                    qtyOrdered: (float) $item->qty,
                    unitPrice: (float) $item->estimated_unit_cost,
                    priceOverrideReason: null,
                    taxRate: 0.0,
                    description: $item->notes,
                );
            }

            $order = $this->purchaseOrders->create(new CreatePurchaseOrderData(
                branchId: (int) $request->branch_id,
                supplierId: $supplierId,
                currencyCode: (string) $request->currency_code,
                exchangeRate: (float) $request->exchange_rate,
                expectedDeliveryDate: $request->needed_by?->format('Y-m-d'),
                notes: $request->notes,
                dropShip: false,
                saleId: null,
                userId: $userId,
                lines: $lines,
            ));

            $updated = $this->requests->update($request, [
                'status' => PurchaseRequestStatus::Converted,
                'converted_purchase_order_id' => $order->id,
                'updated_by' => $userId,
            ]);

            event(new PurchaseRequestConverted($updated, $order));

            return $order;
        });
    }

    /**
     * @param  list<PurchaseRequestLineData>  $lines
     * @return array{subtotal: float, tax_total: float, total: float, functional_total: float}
     */
    private function calculateTotals(array $lines, float $exchangeRate): array
    {
        $subtotal = 0.0;

        foreach ($lines as $line) {
            $subtotal += $line->qty * $line->estimatedUnitCost;
        }

        $total = $subtotal;

        return [
            'subtotal' => round($subtotal, 2),
            'tax_total' => 0.0,
            'total' => round($total, 2),
            'functional_total' => round($total * $exchangeRate, 2),
        ];
    }

    private function createLine(PurchaseRequest $request, PurchaseRequestLineData $line, int $sortOrder): void
    {
        $lineTotal = $line->qty * $line->estimatedUnitCost;

        $request->items()->create([
            'product_variant_id' => $line->variantId,
            'qty' => $line->qty,
            'unit_id' => $line->unitId,
            'preferred_supplier_id' => $line->preferredSupplierId,
            'estimated_unit_cost' => $line->estimatedUnitCost,
            'line_total' => round($lineTotal, 2),
            'notes' => $line->notes,
            'sort_order' => $sortOrder,
        ]);
    }
}
