<?php

declare(strict_types=1);

namespace App\DTOs\Procurement;

final readonly class PurchaseOrderLineData
{
    public function __construct(
        public int $variantId,
        public float $qtyOrdered,
        public float $unitPrice,
        public ?string $priceOverrideReason,
        public float $taxRate,
        public ?string $description,
    ) {}
}
