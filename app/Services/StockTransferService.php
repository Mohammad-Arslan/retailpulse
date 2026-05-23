<?php

declare(strict_types=1);

namespace App\Services;

use App\DTOs\Inventory\DeductStockData;
use App\DTOs\StockTransfer\CreateStockTransferData;
use App\DTOs\StockTransfer\ReceiveStockTransferData;
use App\DTOs\StockTransfer\ReceiveTransferLineData;
use App\DTOs\StockTransfer\TransferLineData;
use App\Enums\StockMovementReason;
use App\Enums\StockTransferStatus;
use App\Models\StockTransfer;
use App\Models\StockTransferItem;
use App\Repositories\Contracts\InventoryRepositoryInterface;
use App\Repositories\Contracts\StockTransferRepositoryInterface;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

final class StockTransferService
{
    public function __construct(
        private readonly StockTransferRepositoryInterface $transfers,
        private readonly InventoryRepositoryInterface $inventories,
        private readonly InventoryService $inventory,
    ) {}

    public function create(CreateStockTransferData $data): StockTransfer
    {
        if ($data->fromWarehouseId === $data->toWarehouseId) {
            throw ValidationException::withMessages([
                'to_warehouse_id' => __('Source and destination warehouses must differ.'),
            ]);
        }

        $this->validateLinesAvailability($data->fromWarehouseId, $data->lines);

        return DB::transaction(function () use ($data) {
            $transfer = $this->transfers->create([
                'reference_no' => $this->transfers->nextReferenceNo(),
                'from_warehouse_id' => $data->fromWarehouseId,
                'to_warehouse_id' => $data->toWarehouseId,
                'status' => StockTransferStatus::Draft,
                'created_by' => $data->userId,
                'notes' => $data->notes,
            ]);

            foreach ($data->lines as $line) {
                $transfer->items()->create([
                    'product_variant_id' => $line->variantId,
                    'batch_id' => $line->batchId,
                    'qty_requested' => $line->quantity,
                    'qty_received' => 0,
                ]);
            }

            return $this->transfers->findByIdWithRelations($transfer->id) ?? $transfer;
        });
    }

    public function ship(StockTransfer $transfer, int $userId): StockTransfer
    {
        if (! $transfer->status->canShip()) {
            throw ValidationException::withMessages([
                'status' => __('Only draft transfers can be shipped.'),
            ]);
        }

        $transfer->load('items');

        $this->validateLinesAvailability(
            $transfer->from_warehouse_id,
            $this->mapItemsToLines($transfer),
        );

        return DB::transaction(function () use ($transfer, $userId) {
            foreach ($transfer->items as $item) {
                $this->inventory->deduct(new DeductStockData(
                    warehouseId: $transfer->from_warehouse_id,
                    variantId: $item->product_variant_id,
                    batchId: $item->batch_id,
                    quantity: $item->qty_requested,
                    reason: StockMovementReason::TransferOut,
                    userId: $userId,
                    referenceType: StockTransfer::class,
                    referenceId: $transfer->id,
                ));
            }

            $this->transfers->update($transfer, [
                'status' => StockTransferStatus::Shipped,
                'shipped_by' => $userId,
                'shipped_at' => now(),
            ]);

            return $this->transfers->findByIdWithRelations($transfer->id) ?? $transfer;
        });
    }

    public function receive(StockTransfer $transfer, ReceiveStockTransferData $data): StockTransfer
    {
        if (! $transfer->status->canReceive()) {
            throw ValidationException::withMessages([
                'status' => __('Only shipped or partially received transfers can be received.'),
            ]);
        }

        $transfer->load('items');
        $receiveLines = $this->resolveReceiveLines($transfer, $data->lines);

        return DB::transaction(function () use ($transfer, $data, $receiveLines) {
            foreach ($receiveLines as $line) {
                /** @var StockTransferItem $item */
                $item = $transfer->items->firstWhere('id', $line->itemId);

                if ($item === null) {
                    continue;
                }

                if ($line->quantity <= 0 || $line->quantity > $item->qtyRemaining()) {
                    throw ValidationException::withMessages([
                        'lines' => __('Receive quantity exceeds remaining in-transit quantity for one or more items.'),
                    ]);
                }

                $this->inventory->applyDelta(
                    warehouseId: $transfer->to_warehouse_id,
                    variantId: $item->product_variant_id,
                    batchId: $item->batch_id,
                    qtyDelta: $line->quantity,
                    reason: StockMovementReason::TransferIn,
                    userId: $data->userId,
                    referenceType: StockTransfer::class,
                    referenceId: $transfer->id,
                );

                $item->update([
                    'qty_received' => $item->qty_received + $line->quantity,
                ]);
            }

            $transfer->refresh()->load('items');

            $allReceived = $transfer->items->every(
                fn (StockTransferItem $item) => $item->isFullyReceived(),
            );

            $this->transfers->update($transfer, [
                'status' => $allReceived
                    ? StockTransferStatus::Received
                    : StockTransferStatus::PartiallyReceived,
                'received_by' => $data->userId,
                'received_at' => $allReceived ? now() : $transfer->received_at,
            ]);

            return $this->transfers->findByIdWithRelations($transfer->id) ?? $transfer;
        });
    }

    /**
     * @param  list<TransferLineData>  $lines
     */
    private function validateLinesAvailability(int $warehouseId, array $lines): void
    {
        foreach ($lines as $line) {
            $available = $this->inventories->availableQuantity(
                $warehouseId,
                $line->variantId,
                $line->batchId,
            );

            if ($available < $line->quantity) {
                throw ValidationException::withMessages([
                    'lines' => __('Cannot transfer more than on-hand quantity for one or more items.'),
                ]);
            }
        }
    }

    /**
     * @return list<TransferLineData>
     */
    private function mapItemsToLines(StockTransfer $transfer): array
    {
        return $transfer->items
            ->map(fn (StockTransferItem $item) => new TransferLineData(
                variantId: $item->product_variant_id,
                batchId: $item->batch_id,
                quantity: $item->qty_requested,
            ))
            ->all();
    }

    /**
     * @param  list<ReceiveTransferLineData>  $lines
     * @return list<ReceiveTransferLineData>
     */
    private function resolveReceiveLines(StockTransfer $transfer, array $lines): array
    {
        if ($lines === []) {
            return $transfer->items
                ->filter(fn (StockTransferItem $item) => $item->qtyRemaining() > 0)
                ->map(fn (StockTransferItem $item) => new ReceiveTransferLineData(
                    itemId: $item->id,
                    quantity: $item->qtyRemaining(),
                ))
                ->values()
                ->all();
        }

        return $lines;
    }
}
