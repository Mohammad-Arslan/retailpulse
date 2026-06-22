<?php

declare(strict_types=1);

namespace App\Services\Procurement;

use App\DTOs\Inventory\ReceiveStockData;
use App\DTOs\Procurement\ReceiveGrnData;
use App\DTOs\Procurement\ReceiveGrnLineData;
use App\Enums\GrnStatus;
use App\Enums\ProcurementDocumentType;
use App\Enums\PurchaseOrderStatus;
use App\Events\Procurement\GoodsReceived;
use App\Models\GoodsReceivingNote;
use App\Models\PurchaseOrder;
use App\Models\PurchaseOrderItem;
use App\Models\Warehouse;
use App\Repositories\Contracts\PurchaseOrderRepositoryInterface;
use App\Services\InventoryService;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

final class GoodsReceivingService
{
    public function __construct(
        private readonly ProcurementDocumentNumberService $documentNumbers,
        private readonly ProcurementConfigService $config,
        private readonly InventoryService $inventory,
        private readonly DropShipService $dropShip,
        private readonly PurchaseOrderRepositoryInterface $orders,
        private readonly SupplierInvoiceService $invoices,
    ) {}

    public function receive(PurchaseOrder $order, ReceiveGrnData $data): GoodsReceivingNote
    {
        if (! $order->status->canReceive()) {
            throw ValidationException::withMessages(['status' => __('Purchase order must be approved before receiving.')]);
        }

        $order->load('items');

        $config = $this->config->resolve($order->branch_id);

        if (! (bool) $order->drop_ship) {
            $warehouse = Warehouse::query()->find($data->warehouseId);
            if ($warehouse === null) {
                throw ValidationException::withMessages(['warehouse_id' => __('Warehouse not found.')]);
            }
            if ((int) $warehouse->branch_id !== (int) $order->branch_id) {
                throw ValidationException::withMessages(['warehouse_id' => __('Warehouse must belong to the purchase order branch.')]);
            }
            if (! $warehouse->is_active) {
                throw ValidationException::withMessages(['warehouse_id' => __('Warehouse is not active.')]);
            }
        }

        return DB::transaction(function () use ($order, $data, $config) {
            $isVirtual = (bool) $order->drop_ship;

            $grn = GoodsReceivingNote::query()->create([
                'branch_id' => $order->branch_id,
                'purchase_order_id' => $order->id,
                'supplier_id' => $order->supplier_id,
                'warehouse_id' => $data->warehouseId,
                'reference_no' => $this->documentNumbers->next($order->branch_id, ProcurementDocumentType::Grn),
                'status' => GrnStatus::Draft,
                'is_virtual' => $isVirtual,
                'notes' => $data->notes,
                'created_by' => $data->userId,
                'updated_by' => $data->userId,
            ]);

            foreach ($data->lines as $line) {
                $this->processLine($order, $grn, $line, $data, $config, $isVirtual);
            }

            $grn->update([
                'status' => GrnStatus::Posted,
                'received_at' => now(),
                'updated_by' => $data->userId,
            ]);

            $this->updatePoReceivedQuantities($order);

            if ($config['auto_close_po'] && $this->isFullyReceived($order)) {
                $this->orders->update($order->fresh() ?? $order, [
                    'status' => PurchaseOrderStatus::Closed,
                    'closed_at' => now(),
                    'updated_by' => $data->userId,
                ]);
            }

            $grn = $grn->fresh(['items', 'purchaseOrder', 'supplier']) ?? $grn;

            if ($isVirtual) {
                $this->dropShip->handleVirtualReceive($grn);
            }

            event(new GoodsReceived($grn));

            $this->invoices->createFromGrnIfMissing($grn, $data->userId);

            return $grn->fresh(['items', 'purchaseOrder', 'supplier', 'supplierInvoices']) ?? $grn;
        });
    }

    /**
     * @param  array<string, mixed>  $config
     */
    private function processLine(
        PurchaseOrder $order,
        GoodsReceivingNote $grn,
        ReceiveGrnLineData $line,
        ReceiveGrnData $data,
        array $config,
        bool $isVirtual,
    ): void {
        /** @var PurchaseOrderItem|null $poItem */
        $poItem = $order->items->firstWhere('id', $line->purchaseOrderItemId);

        if ($poItem === null) {
            throw ValidationException::withMessages([
                'lines' => __('Invalid purchase order line.'),
            ]);
        }

        $remaining = (float) $poItem->qty_ordered - (float) $poItem->qty_received;

        if ($line->qtyReceived <= 0) {
            throw ValidationException::withMessages([
                'lines' => __('Received quantity must be greater than zero.'),
            ]);
        }

        if ($line->qtyReceived < $remaining && ! $config['allow_partial_receive']) {
            throw ValidationException::withMessages([
                'lines' => __('Partial receive is not allowed.'),
            ]);
        }

        if ($line->qtyReceived > $remaining && ! $config['allow_over_receive']) {
            throw ValidationException::withMessages([
                'lines' => __('Over-receive is not allowed for this line.'),
            ]);
        }

        $grnItem = $grn->items()->create([
            'purchase_order_item_id' => $poItem->id,
            'product_variant_id' => $poItem->product_variant_id,
            'qty_received' => $line->qtyReceived,
            'expiry_date' => $line->expiryDate,
            'notes' => $line->notes,
        ]);

        if (! $isVirtual) {
            $receiveData = new ReceiveStockData(
                warehouseId: $data->warehouseId,
                variantId: $poItem->product_variant_id,
                batchId: null,
                quantity: (int) ceil($line->qtyReceived),
                userId: $data->userId,
                notes: $line->notes,
                batchNo: $line->batchNo,
                expiryDate: $line->expiryDate,
            );

            $this->inventory->receive($receiveData);
        }
    }

    private function updatePoReceivedQuantities(PurchaseOrder $order): void
    {
        $order->load('items');

        foreach ($order->items as $item) {
            $received = $item->grnItems()->sum('qty_received');
            $item->update(['qty_received' => $received]);
        }
    }

    private function isFullyReceived(PurchaseOrder $order): bool
    {
        $order->load('items');

        foreach ($order->items as $item) {
            if ((float) $item->qty_received < (float) $item->qty_ordered) {
                return false;
            }
        }

        return $order->items->isNotEmpty();
    }
}
