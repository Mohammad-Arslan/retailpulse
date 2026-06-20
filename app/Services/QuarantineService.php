<?php

declare(strict_types=1);

namespace App\Services;

use App\Enums\StockMovementReason;
use App\Events\InventoryStockChanged;
use App\Models\Inventory;
use App\Repositories\Contracts\InventoryRepositoryInterface;
use App\Repositories\Contracts\StockMovementRepositoryInterface;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

final class QuarantineService
{
    public function __construct(
        private readonly InventoryRepositoryInterface $inventories,
        private readonly StockMovementRepositoryInterface $movements,
        private readonly InventoryService $inventoryService,
    ) {}

    public function addToQuarantine(
        int $warehouseId,
        int $variantId,
        ?int $batchId,
        ?int $binLocationId,
        int $quantity,
        ?int $userId = null,
        ?string $notes = null,
    ): Inventory {
        if ($quantity <= 0) {
            throw ValidationException::withMessages([
                'quantity' => __('Quarantine quantity must be positive.'),
            ]);
        }

        return DB::transaction(function () use (
            $warehouseId,
            $variantId,
            $batchId,
            $binLocationId,
            $quantity,
            $userId,
            $notes,
        ) {
            $inventory = $this->inventories->lockOrCreate(
                $warehouseId,
                $variantId,
                $batchId,
                $binLocationId,
            );

            if ($inventory->availableQuantity() < $quantity) {
                throw ValidationException::withMessages([
                    'quantity' => __('Insufficient sellable stock to quarantine.'),
                ]);
            }

            $previousOnHand = $inventory->quantity_on_hand;
            $previousReserved = $inventory->quantity_reserved;

            $inventory->decrement('quantity_on_hand', $quantity);
            $inventory->increment('quantity_in_quarantine', $quantity);
            $inventory = $inventory->fresh() ?? $inventory;

            $this->movements->create([
                'warehouse_id' => $warehouseId,
                'product_variant_id' => $variantId,
                'batch_id' => $batchId,
                'reason' => StockMovementReason::Adjustment,
                'qty_delta' => -$quantity,
                'quantity_on_hand_after' => $inventory->quantity_on_hand,
                'user_id' => $userId,
                'notes' => $notes ?? 'Moved to quarantine',
            ]);

            event(new InventoryStockChanged(
                $inventory,
                $previousOnHand,
                $previousReserved,
                StockMovementReason::Adjustment,
            ));

            return $inventory;
        });
    }

    public function release(
        int $warehouseId,
        int $variantId,
        ?int $batchId,
        ?int $binLocationId,
        int $quantity,
        ?int $userId = null,
        ?string $notes = null,
    ): Inventory {
        if ($quantity <= 0) {
            throw ValidationException::withMessages([
                'quantity' => __('Release quantity must be positive.'),
            ]);
        }

        return DB::transaction(function () use (
            $warehouseId,
            $variantId,
            $batchId,
            $binLocationId,
            $quantity,
            $userId,
            $notes,
        ) {
            $inventory = $this->inventories->findForUpdate(
                $warehouseId,
                $variantId,
                $batchId,
                $binLocationId,
            );

            if ($inventory === null || $inventory->quantity_in_quarantine < $quantity) {
                throw ValidationException::withMessages([
                    'quantity' => __('Insufficient quarantine quantity to release.'),
                ]);
            }

            $previousOnHand = $inventory->quantity_on_hand;
            $previousReserved = $inventory->quantity_reserved;

            $inventory->decrement('quantity_in_quarantine', $quantity);
            $inventory->increment('quantity_on_hand', $quantity);
            $inventory = $inventory->fresh() ?? $inventory;

            $this->movements->create([
                'warehouse_id' => $warehouseId,
                'product_variant_id' => $variantId,
                'batch_id' => $batchId,
                'reason' => StockMovementReason::QuarantineRelease,
                'qty_delta' => $quantity,
                'quantity_on_hand_after' => $inventory->quantity_on_hand,
                'user_id' => $userId,
                'notes' => $notes ?? 'Released from quarantine',
            ]);

            event(new InventoryStockChanged(
                $inventory,
                $previousOnHand,
                $previousReserved,
                StockMovementReason::QuarantineRelease,
            ));

            $this->inventoryService->checkLowStockAlert($inventory);

            return $inventory;
        });
    }

    public function scrap(
        int $warehouseId,
        int $variantId,
        ?int $batchId,
        ?int $binLocationId,
        int $quantity,
        ?int $userId = null,
        ?string $notes = null,
    ): Inventory {
        if ($quantity <= 0) {
            throw ValidationException::withMessages([
                'quantity' => __('Scrap quantity must be positive.'),
            ]);
        }

        return DB::transaction(function () use (
            $warehouseId,
            $variantId,
            $batchId,
            $binLocationId,
            $quantity,
            $userId,
            $notes,
        ) {
            $inventory = $this->inventories->findForUpdate(
                $warehouseId,
                $variantId,
                $batchId,
                $binLocationId,
            );

            if ($inventory === null || $inventory->quantity_in_quarantine < $quantity) {
                throw ValidationException::withMessages([
                    'quantity' => __('Insufficient quarantine quantity to scrap.'),
                ]);
            }

            $previousOnHand = $inventory->quantity_on_hand;
            $previousReserved = $inventory->quantity_reserved;

            $inventory->decrement('quantity_in_quarantine', $quantity);
            $inventory = $inventory->fresh() ?? $inventory;

            $this->movements->create([
                'warehouse_id' => $warehouseId,
                'product_variant_id' => $variantId,
                'batch_id' => $batchId,
                'reason' => StockMovementReason::QuarantineScrap,
                'qty_delta' => -$quantity,
                'quantity_on_hand_after' => $inventory->quantity_on_hand,
                'user_id' => $userId,
                'notes' => $notes ?? 'Scrapped from quarantine',
            ]);

            event(new InventoryStockChanged(
                $inventory,
                $previousOnHand,
                $previousReserved,
                StockMovementReason::QuarantineScrap,
            ));

            return $inventory;
        });
    }
}
