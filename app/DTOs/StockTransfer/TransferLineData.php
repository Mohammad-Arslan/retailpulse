<?php

declare(strict_types=1);

namespace App\DTOs\StockTransfer;

final readonly class TransferLineData
{
    public function __construct(
        public int $variantId,
        public ?int $batchId,
        public int $quantity,
    ) {}
}
