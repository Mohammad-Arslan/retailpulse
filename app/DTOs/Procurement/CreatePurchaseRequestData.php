<?php

declare(strict_types=1);

namespace App\DTOs\Procurement;

final readonly class CreatePurchaseRequestData
{
    /**
     * @param  list<PurchaseRequestLineData>  $lines
     */
    public function __construct(
        public int $branchId,
        public ?int $warehouseId,
        public string $currencyCode,
        public float $exchangeRate,
        public ?string $neededBy,
        public ?string $notes,
        public int $userId,
        public array $lines,
    ) {}
}
