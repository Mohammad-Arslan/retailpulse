<?php

declare(strict_types=1);

namespace App\DTOs\Inventory;

use App\Enums\StockMovementReason;

final readonly class DeductStockData
{
    public function __construct(
        public int $warehouseId,
        public int $variantId,
        public ?int $batchId,
        public int $quantity,
        public StockMovementReason $reason,
        public ?int $userId = null,
        public ?string $referenceType = null,
        public ?int $referenceId = null,
        public ?string $notes = null,
    ) {}
}
