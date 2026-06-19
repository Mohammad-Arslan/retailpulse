<?php

declare(strict_types=1);

namespace App\Services;

use App\DTOs\Inventory\AdjustStockData;
use App\DTOs\Inventory\DeductStockData;
use App\DTOs\Inventory\ReceiveStockData;
use App\DTOs\Inventory\ReserveStockData;
use App\Enums\CountScopeType;
use App\Enums\CountSessionStatus;
use App\Enums\PickingStrategy;
use App\Enums\SerialStatus;
use App\Enums\StockMovementReason;
use App\Events\InventoryStockChanged;
use App\Events\LowStockAlert;
use App\Models\BinLocation;
use App\Models\CountSession;
use App\Models\Inventory;
use App\Models\ProductSerial;
use App\Models\ProductVariant;
use App\Models\StockReservation;
use App\Models\VariantBranchSetting;
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

        $this->assertBatchProvidedForTrackedVariant($data->variantId, $data->batchId);

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

    public function setOpeningBalance(
        int $warehouseId,
        int $variantId,
        ?int $batchId,
        int $quantity,
        ?int $userId = null,
        ?string $notes = null,
        ?int $binLocationId = null,
    ): Inventory {
        if ($quantity < 0) {
            throw ValidationException::withMessages([
                'quantity' => __('Opening balance quantity cannot be negative.'),
            ]);
        }

        $this->assertTracksInventory($variantId);
        $this->assertBatchProvidedForTrackedVariant($variantId, $batchId);
        $this->assertNotFrozen($warehouseId, $binLocationId);

        if ($binLocationId !== null) {
            $existing = $this->inventories->findForUpdate($warehouseId, $variantId, $batchId, $binLocationId);

            if ($existing !== null && $existing->quantity_on_hand > 0) {
                throw ValidationException::withMessages([
                    'sku' => __('Opening balance already exists for this warehouse, variant, batch, and bin.'),
                ]);
            }
        } elseif ($this->movements->hasOpeningBalance($warehouseId, $variantId, $batchId)) {
            throw ValidationException::withMessages([
                'sku' => __('Opening balance already exists for this warehouse, variant, and batch.'),
            ]);
        }

        return DB::transaction(function () use (
            $warehouseId,
            $variantId,
            $batchId,
            $quantity,
            $userId,
            $notes,
            $binLocationId,
        ) {
            $inventory = $this->inventories->lockOrCreate($warehouseId, $variantId, $batchId, $binLocationId);
            $previousOnHand = $inventory->quantity_on_hand;
            $previousReserved = $inventory->quantity_reserved;

            if ($quantity < $inventory->quantity_reserved) {
                throw ValidationException::withMessages([
                    'quantity' => __('Opening balance cannot be less than reserved quantity.'),
                ]);
            }

            $delta = $quantity - $previousOnHand;

            if ($delta === 0) {
                return $inventory;
            }

            $inventory->update(['quantity_on_hand' => $quantity]);

            $this->movements->create([
                'warehouse_id' => $warehouseId,
                'product_variant_id' => $variantId,
                'batch_id' => $batchId,
                'reason' => StockMovementReason::OpeningBalance,
                'qty_delta' => $delta,
                'quantity_on_hand_after' => $quantity,
                'user_id' => $userId,
                'notes' => $notes ?? 'Opening balance import',
            ]);

            $inventory = $inventory->fresh() ?? $inventory;

            $this->dispatchStockChanged(
                $inventory,
                $previousOnHand,
                $previousReserved,
                StockMovementReason::OpeningBalance,
            );

            $this->checkLowStockAlert($inventory);

            return $inventory;
        });
    }

    public function deduct(DeductStockData $data): Inventory
    {
        if ($data->reason === StockMovementReason::Sale) {
            $this->assertPosSalesAllowed($data->warehouseId);
        }

        $variant = ProductVariant::query()->with('product')->find($data->variantId);
        $this->assertTracksInventory($data->variantId);

        if ($data->quantity <= 0) {
            throw ValidationException::withMessages([
                'quantity' => __('Deduction quantity must be positive.'),
            ]);
        }

        if ($data->batchId !== null) {
            return $this->deductFromInventoryRow($data, $data->batchId, $data->quantity);
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
            $inventory = $this->deductFromInventoryRow(
                $data,
                $line['batch_id'],
                $line['quantity'],
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
        $this->assertBatchProvidedForTrackedVariant($data->variantId, $data->batchId);

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

            $previousOnHand = $inventory->quantity_on_hand;
            $previousReserved = $inventory->quantity_reserved;

            if ($inventory->availableQuantity() < $data->quantity) {
                throw ValidationException::withMessages([
                    'quantity' => __('Insufficient stock available to reserve.'),
                ]);
            }

            $inventory->increment('quantity_reserved', $data->quantity, []);
            $inventory = $inventory->fresh() ?? $inventory;

            $this->movements->create([
                'warehouse_id' => $data->warehouseId,
                'product_variant_id' => $data->variantId,
                'batch_id' => $data->batchId,
                'reason' => StockMovementReason::Reserved,
                'qty_delta' => 0,
                'quantity_on_hand_after' => $inventory->quantity_on_hand,
                'reference_type' => $data->referenceType,
                'reference_id' => $data->referenceId,
                'user_id' => $data->userId,
                'notes' => null,
            ]);

            StockReservation::query()->create([
                'warehouse_id' => $data->warehouseId,
                'product_variant_id' => $data->variantId,
                'batch_id' => $data->batchId,
                'quantity' => $data->quantity,
                'reference_type' => $data->referenceType,
                'reference_id' => $data->referenceId,
                'expires_at' => now()->addMinutes((int) config('inventory.reservation_ttl_minutes', 30)),
            ]);

            $this->dispatchStockChanged(
                $inventory,
                $previousOnHand,
                $previousReserved,
                StockMovementReason::Reserved,
            );

            return $inventory;
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

            $previousOnHand = $inventory->quantity_on_hand;
            $previousReserved = $inventory->quantity_reserved;

            $inventory->decrement('quantity_reserved', $data->quantity, []);
            $inventory = $inventory->fresh() ?? $inventory;

            $this->movements->create([
                'warehouse_id' => $data->warehouseId,
                'product_variant_id' => $data->variantId,
                'batch_id' => $data->batchId,
                'reason' => StockMovementReason::ReservationReleased,
                'qty_delta' => 0,
                'quantity_on_hand_after' => $inventory->quantity_on_hand,
                'reference_type' => $data->referenceType,
                'reference_id' => $data->referenceId,
                'user_id' => $data->userId,
                'notes' => null,
            ]);

            $this->markReservationsReleased($data, $data->quantity);

            $this->dispatchStockChanged(
                $inventory,
                $previousOnHand,
                $previousReserved,
                StockMovementReason::ReservationReleased,
            );

            return $inventory;
        });
    }

    public function releaseExpiredReservations(): int
    {
        $released = 0;

        StockReservation::query()
            ->whereNull('released_at')
            ->where('expires_at', '<=', now())
            ->orderBy('id')
            ->chunkById(100, function ($reservations) use (&$released): void {
                foreach ($reservations as $reservation) {
                    $this->release(new ReserveStockData(
                        warehouseId: (int) $reservation->warehouse_id,
                        variantId: (int) $reservation->product_variant_id,
                        batchId: $reservation->batch_id !== null ? (int) $reservation->batch_id : null,
                        quantity: (int) $reservation->quantity,
                        userId: null,
                        referenceType: $reservation->reference_type,
                        referenceId: $reservation->reference_id !== null
                            ? (int) $reservation->reference_id
                            : null,
                    ));

                    $released++;
                }
            });

        return $released;
    }

    public function availableQuantity(int $warehouseId, int $variantId, ?int $batchId = null): int
    {
        return $this->inventories->availableQuantity($warehouseId, $variantId, $batchId);
    }

    /**
     * @param  list<array{variant_id: int, batch_id: int|null, quantity: int}>  $lines
     * @return list<array{variant_id: int, batch_id: int|null, requested: int, available: int, can_sell: bool, sufficient: bool}>
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
                    'can_sell' => true,
                    'sufficient' => true,
                ];

                continue;
            }

            $available = $this->inventories->totalAvailableQuantity($warehouseId, $variantId, $batchId);
            $sufficient = $available >= $requested;

            $results[] = [
                'variant_id' => $variantId,
                'batch_id' => $batchId,
                'requested' => $requested,
                'available' => $available,
                'can_sell' => $sufficient,
                'sufficient' => $sufficient,
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
        ?int $binLocationId = null,
    ): Inventory {
        $this->assertTracksInventory($variantId);

        if ($reason === StockMovementReason::Sale) {
            $this->assertPosSalesAllowed($warehouseId);
        }

        if ($qtyDelta === 0 && $reason->affectsOnHand()) {
            throw ValidationException::withMessages([
                'quantity' => __('Quantity must not be zero.'),
            ]);
        }

        $this->assertNotFrozen($warehouseId, $binLocationId);

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
            $binLocationId,
        ) {
            $inventory = $this->inventories->lockOrCreate($warehouseId, $variantId, $batchId, $binLocationId);
            $previousOnHand = $inventory->quantity_on_hand;
            $previousReserved = $inventory->quantity_reserved;
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

            $this->dispatchStockChanged($inventory, $previousOnHand, $previousReserved, $reason);

            $this->checkLowStockAlert($inventory);

            return $inventory;
        });
    }

    public function checkLowStockAlert(Inventory $inventory): void
    {
        $inventory->loadMissing(['warehouse', 'variant']);

        $branchId = $inventory->warehouse?->branch_id;

        if ($branchId === null) {
            return;
        }

        $setting = VariantBranchSetting::query()
            ->where('branch_id', $branchId)
            ->where('product_variant_id', $inventory->product_variant_id)
            ->first();

        $reorderPoint = $setting?->reorder_point ?? $inventory->variant?->reorder_point;

        if ($reorderPoint === null) {
            return;
        }

        $totalOnHand = (int) Inventory::query()
            ->where('warehouse_id', $inventory->warehouse_id)
            ->where('product_variant_id', $inventory->product_variant_id)
            ->sum('quantity_on_hand');

        if ($totalOnHand <= $reorderPoint && $inventory->variant !== null) {
            event(new LowStockAlert(
                inventory: $inventory,
                variant: $inventory->variant,
                reorderPoint: $reorderPoint,
                quantityOnHand: $totalOnHand,
            ));
        }
    }

    private function assertNotFrozen(int $warehouseId, ?int $binLocationId = null): void
    {
        $zoneId = null;

        if ($binLocationId !== null) {
            $zoneId = BinLocation::query()
                ->whereKey($binLocationId)
                ->value('warehouse_zone_id');
        }

        $query = CountSession::query()
            ->where('warehouse_id', $warehouseId)
            ->where('freeze_mode', true)
            ->whereIn('status', [
                CountSessionStatus::InProgress,
                CountSessionStatus::UnderReview,
                CountSessionStatus::Approved,
            ]);

        if ($binLocationId !== null || $zoneId !== null) {
            $query->where(function ($q) use ($zoneId) {
                $q->where('scope_type', CountScopeType::Full);

                if ($zoneId !== null) {
                    $q->orWhere(function ($inner) use ($zoneId) {
                        $inner->where('scope_type', CountScopeType::Zone)
                            ->where('scope_id', $zoneId);
                    });
                }
            });
        }

        if ($query->exists()) {
            throw ValidationException::withMessages([
                'warehouse_id' => __('Inventory movements are frozen during an active cycle count.'),
            ]);
        }
    }

    private function deductFromInventoryRow(DeductStockData $data, ?int $batchId, int $quantity): Inventory
    {
        return DB::transaction(function () use ($data, $batchId, $quantity) {
            $inventory = $this->inventories->lockOrCreate(
                $data->warehouseId,
                $data->variantId,
                $batchId,
            );

            $previousOnHand = $inventory->quantity_on_hand;
            $previousReserved = $inventory->quantity_reserved;

            if ($inventory->availableQuantity() < $quantity) {
                throw ValidationException::withMessages([
                    'quantity' => __('Insufficient stock on hand.'),
                ]);
            }

            $reservedRelease = min($quantity, $inventory->quantity_reserved);
            $newOnHand = $inventory->quantity_on_hand - $quantity;
            $newReserved = $inventory->quantity_reserved - $reservedRelease;

            $inventory->update([
                'quantity_on_hand' => $newOnHand,
                'quantity_reserved' => $newReserved,
            ]);

            $this->movements->create([
                'warehouse_id' => $data->warehouseId,
                'product_variant_id' => $data->variantId,
                'batch_id' => $batchId,
                'reason' => $data->reason,
                'qty_delta' => -$quantity,
                'quantity_on_hand_after' => $newOnHand,
                'reference_type' => $data->referenceType,
                'reference_id' => $data->referenceId,
                'user_id' => $data->userId,
                'notes' => $data->notes,
            ]);

            if ($reservedRelease > 0) {
                $this->markReservationsReleased(
                    new ReserveStockData(
                        warehouseId: $data->warehouseId,
                        variantId: $data->variantId,
                        batchId: $batchId,
                        quantity: $reservedRelease,
                        referenceType: $data->referenceType,
                        referenceId: $data->referenceId,
                    ),
                    $reservedRelease,
                );
            }

            $inventory = $inventory->fresh() ?? $inventory;

            $this->dispatchStockChanged(
                $inventory,
                $previousOnHand,
                $previousReserved,
                $data->reason,
            );

            return $inventory;
        });
    }

    private function assertPosSalesAllowed(int $warehouseId): void
    {
        $warehouse = Warehouse::query()->with('branch')->find($warehouseId);
        $cutover = $warehouse?->branch?->cutover_date;

        if ($cutover !== null && now()->lt($cutover)) {
            throw ValidationException::withMessages([
                'cutover_date' => __('Live POS sales are blocked until the go-live cutover date.'),
            ]);
        }
    }

    private function assertBatchProvidedForTrackedVariant(int $variantId, ?int $batchId): void
    {
        $variant = ProductVariant::query()->with('product')->find($variantId);

        if ($variant?->product?->track_batches && $batchId === null) {
            throw ValidationException::withMessages([
                'batch_id' => __('Batch is required for batch-tracked products.'),
            ]);
        }
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

    private function dispatchStockChanged(
        Inventory $inventory,
        int $previousOnHand,
        int $previousReserved,
        StockMovementReason $reason,
    ): void {
        event(new InventoryStockChanged(
            inventory: $inventory,
            previousOnHand: $previousOnHand,
            previousReserved: $previousReserved,
            reason: $reason,
        ));
    }

    private function markReservationsReleased(ReserveStockData $data, int $quantity): void
    {
        $remaining = $quantity;

        StockReservation::query()
            ->whereNull('released_at')
            ->where('warehouse_id', $data->warehouseId)
            ->where('product_variant_id', $data->variantId)
            ->when(
                $data->batchId === null,
                fn ($q) => $q->whereNull('batch_id'),
                fn ($q) => $q->where('batch_id', $data->batchId),
            )
            ->when($data->referenceType !== null, fn ($q) => $q->where('reference_type', $data->referenceType))
            ->when($data->referenceId !== null, fn ($q) => $q->where('reference_id', $data->referenceId))
            ->orderBy('expires_at')
            ->orderBy('id')
            ->get()
            ->each(function (StockReservation $reservation) use (&$remaining): void {
                if ($remaining <= 0) {
                    return;
                }

                if ((int) $reservation->quantity <= $remaining) {
                    $reservation->update(['released_at' => now()]);
                    $remaining -= (int) $reservation->quantity;

                    return;
                }

                $reservation->update([
                    'quantity' => (int) $reservation->quantity - $remaining,
                ]);
                $remaining = 0;
            });
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
