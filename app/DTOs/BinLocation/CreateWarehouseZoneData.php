<?php

declare(strict_types=1);

namespace App\DTOs\BinLocation;

final readonly class CreateWarehouseZoneData
{
    public function __construct(
        public int $warehouseId,
        public string $name,
        public ?int $capacityLimit,
    ) {}

    /**
     * @param  array{name: string, capacity_limit?: int|null}  $row
     */
    public static function fromArray(int $warehouseId, array $row): self
    {
        return new self(
            warehouseId: $warehouseId,
            name: (string) $row['name'],
            capacityLimit: isset($row['capacity_limit']) ? (int) $row['capacity_limit'] : null,
        );
    }
}
