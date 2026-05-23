<?php

declare(strict_types=1);

namespace App\DTOs\StockTransfer;

final readonly class ReceiveStockTransferData
{
    /**
     * @param  list<ReceiveTransferLineData>  $lines  Empty = receive all remaining quantities.
     */
    public function __construct(
        public int $transferId,
        public int $userId,
        public array $lines = [],
    ) {}
}
