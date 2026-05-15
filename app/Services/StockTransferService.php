<?php

declare(strict_types=1);

namespace App\Services;

use App\DTOs\Inventory\DeductStockData;
use App\DTOs\StockTransfer\CreateStockTransferData;
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
                    'quantity' => $line->quantity,
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
                    quantity: $item->quantity,
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

    public function receive(StockTransfer $transfer, int $userId): StockTransfer
    {
        if (! $transfer->status->canReceive()) {
            throw ValidationException::withMessages([
                'status' => __('Only shipped transfers can be received.'),
            ]);
        }

        $transfer->load('items');

        return DB::transaction(function () use ($transfer, $userId) {
            foreach ($transfer->items as $item) {
                $this->inventory->applyDelta(
                    warehouseId: $transfer->to_warehouse_id,
                    variantId: $item->product_variant_id,
                    batchId: $item->batch_id,
                    qtyDelta: $item->quantity,
                    reason: StockMovementReason::TransferIn,
                    userId: $userId,
                    referenceType: StockTransfer::class,
                    referenceId: $transfer->id,
                );
            }

            $this->transfers->update($transfer, [
                'status' => StockTransferStatus::Received,
                'received_by' => $userId,
                'received_at' => now(),
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
                quantity: $item->quantity,
            ))
            ->all();
    }
}
