<?php

declare(strict_types=1);

namespace App\DTOs\StockTransfer;

final readonly class ReceiveTransferLineData
{
    public function __construct(
        public int $itemId,
        public int $quantity,
    ) {}
}
