<?php

declare(strict_types=1);

namespace App\DTOs\BinLocation;

final readonly class BinTransferData
{
    public function __construct(
        public int $warehouseId,
        public int $fromBinId,
        public int $toBinId,
        public int $variantId,
        public ?int $batchId,
        public int $quantity,
        public ?int $userId,
        public ?string $notes = null,
    ) {}
}
