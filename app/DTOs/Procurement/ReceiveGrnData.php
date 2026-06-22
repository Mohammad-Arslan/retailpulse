<?php

declare(strict_types=1);

namespace App\DTOs\Procurement;

final readonly class ReceiveGrnData
{
    /**
     * @param  list<ReceiveGrnLineData>  $lines
     */
    public function __construct(
        public int $purchaseOrderId,
        public int $warehouseId,
        public int $userId,
        public ?string $notes,
        public array $lines,
    ) {}
}
