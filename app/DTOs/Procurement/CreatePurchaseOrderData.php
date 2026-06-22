<?php

declare(strict_types=1);

namespace App\DTOs\Procurement;

final readonly class CreatePurchaseOrderData
{
    /**
     * @param  list<PurchaseOrderLineData>  $lines
     */
    public function __construct(
        public int $branchId,
        public int $supplierId,
        public string $currencyCode,
        public float $exchangeRate,
        public ?string $expectedDeliveryDate,
        public ?string $notes,
        public bool $dropShip,
        public ?int $saleId,
        public int $userId,
        public array $lines,
    ) {}
}
