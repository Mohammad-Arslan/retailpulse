<?php

declare(strict_types=1);

namespace App\DTOs\Procurement;

final readonly class ReceiveGrnLineData
{
    public function __construct(
        public int $purchaseOrderItemId,
        public float $qtyReceived,
        public ?string $batchNo,
        public ?string $expiryDate,
        public ?string $notes,
    ) {}
}
