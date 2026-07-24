<?php

declare(strict_types=1);

namespace App\DTOs\Procurement;

final readonly class PurchaseRequestLineData
{
    public function __construct(
        public int $variantId,
        public float $qty,
        public float $estimatedUnitCost,
        public ?int $unitId,
        public ?int $preferredSupplierId,
        public ?string $notes,
    ) {}
}
