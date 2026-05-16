<?php

declare(strict_types=1);

namespace App\Services;

use App\DTOs\Inventory\AdjustStockData;
use App\DTOs\Inventory\DeductStockData;
use App\DTOs\Inventory\ReceiveStockData;
use App\DTOs\Inventory\ReserveStockData;
use App\Enums\PickingStrategy;
use App\Enums\SerialStatus;
use App\Enums\StockMovementReason;
use App\Events\InventoryStockChanged;
use App\Models\Inventory;
use App\Models\ProductSerial;
use App\Models\ProductVariant;
use App\Models\Warehouse;
use App\Repositories\Contracts\InventoryRepositoryInterface;
use App\Repositories\Contracts\StockMovementRepositoryInterface;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

final class InventoryService
{
    public function __construct(
        private readonly InventoryRepositoryInterface $inventories,
        private readonly StockMovementRepositoryInterface $movements,
    ) {}

    public function receive(ReceiveStockData $data): Inventory
    {
        if ($data->serialNumbers !== []) {
            $this->registerSerials($data->variantId, $data->serialNumbers);
        }

        return $this->applyDelta(
            warehouseId: $data->warehouseId,
            variantId: $data->variantId,
            batchId: $data->batchId,
            qtyDelta: abs($data->quantity),
            reason: StockMovementReason::PurchaseReceive,
            userId: $data->userId,
            notes: $data->notes,
        );
    }

    public function adjust(AdjustStockData $data): Inventory
    {
        if ($data->quantity === 0) {
            throw ValidationException::withMessages([
                'quantity' => __('Quantity must not be zero.'),
            ]);
        }

        $reason = $data->reason;
        $qtyDelta = $data->quantity;

        if ($reason === StockMovementReason::Damaged && $qtyDelta > 0) {
            $qtyDelta = -$qtyDelta;
        }

        return $this->applyDelta(
            warehouseId: $data->warehouseId,
            variantId: $data->variantId,
            batchId: $data->batchId,
            qtyDelta: $qtyDelta,
            reason: $reason,
            userId: $data->userId,
            notes: $data->notes,
        );
    }

    public function deduct(DeductStockData $data): Inventory
    {
        $variant = ProductVariant::query()->with('product')->find($data->variantId);
        $this->assertTracksInventory($data->variantId);

        if ($data->quantity <= 0) {
            throw ValidationException::withMessages([
                'quantity' => __('Deduction quantity must be positive.'),
            ]);
        }

        if ($data->batchId !== null) {
            return $this->applyDelta(
                warehouseId: $data->warehouseId,
                variantId: $data->variantId,
                batchId: $data->batchId,
                qtyDelta: -$data->quantity,
                reason: $data->reason,
                userId: $data->userId,
                referenceType: $data->referenceType,
                referenceId: $data->referenceId,
                notes: $data->notes,
            );
        }

        $warehouse = Warehouse::query()->with('branch')->findOrFail($data->warehouseId);
        $strategy = $warehouse->branch?->picking_strategy ?? PickingStrategy::Fifo;
        $trackBatches = (bool) ($variant?->product?->track_batches ?? false);

        $lines = $this->inventories->allocateDeductionLines(
            $data->warehouseId,
            $data->variantId,
            $data->quantity,
            $strategy,
            $trackBatches,
        );

        $inventory = null;

        foreach ($lines as $line) {
            $inventory = $this->applyDelta(
                warehouseId: $data->warehouseId,
                variantId: $data->variantId,
                batchId: $line['batch_id'],
                qtyDelta: -$line['quantity'],
                reason: $data->reason,
                userId: $data->userId,
                referenceType: $data->referenceType,
                referenceId: $data->referenceId,
                notes: $data->notes,
            );
        }

        return $inventory ?? $this->inventories->lockOrCreate(
            $data->warehouseId,
            $data->variantId,
            null,
        );
    }

    public function reserve(ReserveStockData $data): Inventory
    {
        $this->assertTracksInventory($data->variantId);

        if ($data->quantity <= 0) {
            throw ValidationException::withMessages([
                'quantity' => __('Reserve quantity must be positive.'),
            ]);
        }

        return DB::transaction(function () use ($data) {
            $inventory = $this->inventories->lockOrCreate(
                $data->warehouseId,
                $data->variantId,
                $data->batchId,
            );

            if ($inventory->availableQuantity() < $data->quantity) {
                throw ValidationException::withMessages([
                    'quantity' => __('Insufficient stock available to reserve.'),
                ]);
            }

            $inventory->increment('quantity_reserved', $data->quantity, []);

            return $inventory->fresh() ?? $inventory;
        });
    }

    public function release(ReserveStockData $data): Inventory
    {
        if ($data->quantity <= 0) {
            throw ValidationException::withMessages([
                'quantity' => __('Release quantity must be positive.'),
            ]);
        }

        return DB::transaction(function () use ($data) {
            $inventory = $this->inventories->findForUpdate(
                $data->warehouseId,
                $data->variantId,
                $data->batchId,
            );

            if ($inventory === null || $inventory->quantity_reserved < $data->quantity) {
                throw ValidationException::withMessages([
                    'quantity' => __('Cannot release more than reserved quantity.'),
                ]);
            }

            $inventory->decrement('quantity_reserved', $data->quantity, []);

            return $inventory->fresh() ?? $inventory;
        });
    }

    public function availableQuantity(int $warehouseId, int $variantId, ?int $batchId = null): int
    {
        return $this->inventories->availableQuantity($warehouseId, $variantId, $batchId);
    }

    /**
     * @param  list<array{variant_id: int, batch_id: int|null, quantity: int}>  $lines
     * @return list<array{variant_id: int, batch_id: int|null, requested: int, available: int, sufficient: bool}>
     */
    public function checkAvailability(int $warehouseId, array $lines): array
    {
        $results = [];

        foreach ($lines as $line) {
            $variantId = (int) $line['variant_id'];
            $batchId = isset($line['batch_id']) ? (int) $line['batch_id'] : null;
            $requested = (int) $line['quantity'];

            $variant = ProductVariant::query()->with('product')->find($variantId);

            if ($variant === null || $variant->product === null || ! $variant->product->tracksInventory()) {
                $results[] = [
                    'variant_id' => $variantId,
                    'batch_id' => $batchId,
                    'requested' => $requested,
                    'available' => PHP_INT_MAX,
                    'sufficient' => true,
                ];

                continue;
            }

            $available = $this->availableQuantity($warehouseId, $variantId, $batchId);

            $results[] = [
                'variant_id' => $variantId,
                'batch_id' => $batchId,
                'requested' => $requested,
                'available' => $available,
                'sufficient' => $available >= $requested,
            ];
        }

        return $results;
    }

    public function applyDelta(
        int $warehouseId,
        int $variantId,
        ?int $batchId,
        int $qtyDelta,
        StockMovementReason $reason,
        ?int $userId = null,
        ?string $referenceType = null,
        ?int $referenceId = null,
        ?string $notes = null,
    ): Inventory {
        $this->assertTracksInventory($variantId);

        if ($qtyDelta === 0) {
            throw ValidationException::withMessages([
                'quantity' => __('Quantity must not be zero.'),
            ]);
        }

        return DB::transaction(function () use (
            $warehouseId,
            $variantId,
            $batchId,
            $qtyDelta,
            $reason,
            $userId,
            $referenceType,
            $referenceId,
            $notes,
        ) {
            $inventory = $this->inventories->lockOrCreate($warehouseId, $variantId, $batchId);
            $previousOnHand = $inventory->quantity_on_hand;
            $newOnHand = $previousOnHand + $qtyDelta;

            if ($newOnHand < 0) {
                throw ValidationException::withMessages([
                    'quantity' => __('Insufficient stock on hand.'),
                ]);
            }

            if ($qtyDelta < 0 && $newOnHand < $inventory->quantity_reserved) {
                throw ValidationException::withMessages([
                    'quantity' => __('Cannot reduce stock below reserved quantity.'),
                ]);
            }

            $inventory->update(['quantity_on_hand' => $newOnHand]);

            $this->movements->create([
                'warehouse_id' => $warehouseId,
                'product_variant_id' => $variantId,
                'batch_id' => $batchId,
                'reason' => $reason,
                'qty_delta' => $qtyDelta,
                'quantity_on_hand_after' => $newOnHand,
                'reference_type' => $referenceType,
                'reference_id' => $referenceId,
                'user_id' => $userId,
                'notes' => $notes,
            ]);

            $inventory = $inventory->fresh() ?? $inventory;

            event(new InventoryStockChanged($inventory, $previousOnHand));

            return $inventory;
        });
    }

    private function assertTracksInventory(int $variantId): void
    {
        $variant = ProductVariant::query()->with('product')->find($variantId);

        if ($variant?->product === null || ! $variant->product->tracksInventory()) {
            throw ValidationException::withMessages([
                'product_variant_id' => __('This product does not track inventory.'),
            ]);
        }
    }

    /**
     * @param  list<string>  $serialNumbers
     */
    private function registerSerials(int $variantId, array $serialNumbers): void
    {
        $variant = ProductVariant::query()->with('product')->find($variantId);

        if ($variant?->product === null || ! $variant->product->track_serials) {
            throw ValidationException::withMessages([
                'serial_numbers' => __('This product does not track serial numbers.'),
            ]);
        }

        foreach ($serialNumbers as $serialNumber) {
            $exists = ProductSerial::query()
                ->where('product_variant_id', $variantId)
                ->where('serial_number', $serialNumber)
                ->exists();

            if ($exists) {
                throw ValidationException::withMessages([
                    'serial_numbers' => __('Serial number :serial already exists.', ['serial' => $serialNumber]),
                ]);
            }

            ProductSerial::query()->create([
                'product_variant_id' => $variantId,
                'serial_number' => $serialNumber,
                'status' => SerialStatus::Available,
            ]);
        }
    }
}
