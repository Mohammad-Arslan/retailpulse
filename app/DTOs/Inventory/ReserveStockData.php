<?php

declare(strict_types=1);

namespace App\DTOs\Inventory;

final readonly class ReserveStockData
{
    public function __construct(
        public int $warehouseId,
        public int $variantId,
        public ?int $batchId,
        public int $quantity,
        public ?int $userId = null,
        public ?string $referenceType = null,
        public ?int $referenceId = null,
    ) {}
}
