<?php

declare(strict_types=1);

namespace App\Services;

use App\Models\Inventory;

final class QuarantineService
{
    public function __construct(
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
        return $this->inventoryService->moveToQuarantine(
            $warehouseId,
            $variantId,
            $batchId,
            $binLocationId,
            $quantity,
            $userId,
            $notes,
        );
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
        return $this->inventoryService->releaseFromQuarantine(
            $warehouseId,
            $variantId,
            $batchId,
            $binLocationId,
            $quantity,
            $userId,
            $notes,
        );
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
        return $this->inventoryService->scrapFromQuarantine(
            $warehouseId,
            $variantId,
            $batchId,
            $binLocationId,
            $quantity,
            $userId,
            $notes,
        );
    }
}
