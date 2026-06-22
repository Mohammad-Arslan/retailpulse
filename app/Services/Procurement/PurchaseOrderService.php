<?php

declare(strict_types=1);

namespace App\Services\Procurement;

use App\DTOs\Procurement\CreatePurchaseOrderData;
use App\DTOs\Procurement\PurchaseOrderLineData;
use App\Enums\ProcurementDocumentType;
use App\Enums\PurchaseOrderStatus;
use App\Events\Procurement\PurchaseOrderApproved;
use App\Models\PurchaseOrder;
use App\Models\User;
use App\Repositories\Contracts\PurchaseOrderRepositoryInterface;
use App\Services\Procurement\Approval\PoApprovalStrategyFactory;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

final class PurchaseOrderService
{
    public function __construct(
        private readonly PurchaseOrderRepositoryInterface $orders,
        private readonly ProcurementDocumentNumberService $documentNumbers,
        private readonly ProcurementConfigService $config,
        private readonly SupplierService $suppliers,
        private readonly PoApprovalStrategyFactory $approvalFactory,
    ) {}

    public function create(CreatePurchaseOrderData $data): PurchaseOrder
    {
        if ($data->lines === []) {
            throw ValidationException::withMessages(['lines' => __('At least one line item is required.')]);
        }

        return DB::transaction(function () use ($data) {
            $totals = $this->calculateTotals($data->lines, $data->exchangeRate);

            $order = $this->orders->create([
                'branch_id' => $data->branchId,
                'supplier_id' => $data->supplierId,
                'reference_no' => $this->documentNumbers->next($data->branchId, ProcurementDocumentType::PurchaseOrder),
                'status' => PurchaseOrderStatus::Draft,
                'currency_code' => $data->currencyCode,
                'exchange_rate' => $data->exchangeRate,
                'subtotal' => $totals['subtotal'],
                'tax_total' => $totals['tax_total'],
                'total' => $totals['total'],
                'functional_total' => $totals['functional_total'],
                'expected_delivery_date' => $data->expectedDeliveryDate,
                'drop_ship' => $data->dropShip,
                'sale_id' => $data->saleId,
                'notes' => $data->notes,
                'created_by' => $data->userId,
                'updated_by' => $data->userId,
            ]);

            foreach ($data->lines as $index => $line) {
                $this->createLine($order, $line, $data->exchangeRate, $index, $data->supplierId, $data->userId);
            }

            return $this->orders->findByIdWithRelations($order->id) ?? $order;
        });
    }

    public function submit(PurchaseOrder $order, int $userId): PurchaseOrder
    {
        if (! $order->status->canSubmit()) {
            throw ValidationException::withMessages(['status' => __('Only draft purchase orders can be submitted.')]);
        }

        return $this->orders->update($order, [
            'status' => PurchaseOrderStatus::Submitted,
            'submitted_at' => now(),
            'updated_by' => $userId,
        ]);
    }

    public function approve(PurchaseOrder $order, User $approver, ?string $managerPin = null): PurchaseOrder
    {
        if (! $order->status->canApprove()) {
            throw ValidationException::withMessages(['status' => __('Only submitted purchase orders can be approved.')]);
        }

        if ($this->config->requiresApproval((float) $order->total, $order->branch_id)) {
            $this->approvalFactory->make()->approve($order, $approver, $managerPin);
        }

        $updated = DB::transaction(function () use ($order, $approver) {
            $result = $this->orders->update($order, [
                'status' => PurchaseOrderStatus::Approved,
                'approved_by' => $approver->id,
                'approved_at' => now(),
                'updated_by' => $approver->id,
            ]);

            event(new PurchaseOrderApproved($result));

            return $result;
        });

        return $this->orders->findByIdWithRelations($updated->id) ?? $updated;
    }

    public function reject(PurchaseOrder $order, int $userId, string $reason): PurchaseOrder
    {
        if (! $order->status->canReject()) {
            throw ValidationException::withMessages(['status' => __('Only submitted purchase orders can be rejected.')]);
        }

        return $this->orders->update($order, [
            'status' => PurchaseOrderStatus::Rejected,
            'rejected_by' => $userId,
            'rejected_at' => now(),
            'rejection_reason' => $reason,
            'updated_by' => $userId,
        ]);
    }

    public function cancel(PurchaseOrder $order, int $userId): PurchaseOrder
    {
        if (! $order->status->canCancel()) {
            throw ValidationException::withMessages(['status' => __('This purchase order cannot be cancelled.')]);
        }

        return $this->orders->update($order, [
            'status' => PurchaseOrderStatus::Cancelled,
            'cancelled_at' => now(),
            'updated_by' => $userId,
        ]);
    }

    public function close(PurchaseOrder $order, int $userId): PurchaseOrder
    {
        if (! $order->status->canClose()) {
            throw ValidationException::withMessages(['status' => __('Only approved purchase orders can be closed.')]);
        }

        return $this->orders->update($order, [
            'status' => PurchaseOrderStatus::Closed,
            'closed_at' => now(),
            'updated_by' => $userId,
        ]);
    }

    /**
     * @param  list<PurchaseOrderLineData>  $lines
     * @return array{subtotal: float, tax_total: float, total: float, functional_total: float}
     */
    private function calculateTotals(array $lines, float $exchangeRate): array
    {
        $subtotal = 0.0;
        $taxTotal = 0.0;

        foreach ($lines as $line) {
            $lineSubtotal = $line->qtyOrdered * $line->unitPrice;
            $subtotal += $lineSubtotal;
            $taxTotal += $lineSubtotal * ($line->taxRate / 100);
        }

        $total = $subtotal + $taxTotal;

        return [
            'subtotal' => round($subtotal, 2),
            'tax_total' => round($taxTotal, 2),
            'total' => round($total, 2),
            'functional_total' => round($total * $exchangeRate, 2),
        ];
    }

    private function createLine(
        PurchaseOrder $order,
        PurchaseOrderLineData $line,
        float $exchangeRate,
        int $sortOrder,
        int $supplierId,
        int $userId,
    ): void {
        $listPrice = $this->suppliers->resolvePrice($supplierId, $line->variantId, $line->qtyOrdered);
        $unitPrice = $line->unitPrice;

        if ($listPrice !== null && abs($listPrice - $unitPrice) > 0.0001) {
            if ($line->priceOverrideReason === null || $line->priceOverrideReason === '') {
                throw ValidationException::withMessages([
                    'lines' => __('Price override requires a reason for variant #:id.', ['id' => $line->variantId]),
                ]);
            }
        } elseif ($listPrice !== null) {
            $unitPrice = $listPrice;
        }

        $lineSubtotal = $line->qtyOrdered * $unitPrice;
        $lineTax = $lineSubtotal * ($line->taxRate / 100);
        $lineTotal = $lineSubtotal + $lineTax;

        $order->items()->create([
            'product_variant_id' => $line->variantId,
            'description' => $line->description,
            'qty_ordered' => $line->qtyOrdered,
            'unit_price' => $unitPrice,
            'price_override_reason' => $line->priceOverrideReason,
            'tax_rate' => $line->taxRate,
            'line_total' => round($lineTotal, 2),
            'functional_line_total' => round($lineTotal * $exchangeRate, 2),
            'currency_code' => $order->currency_code,
            'exchange_rate' => $exchangeRate,
            'sort_order' => $sortOrder,
        ]);
    }
}
