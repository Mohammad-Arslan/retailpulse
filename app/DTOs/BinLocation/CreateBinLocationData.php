<?php

declare(strict_types=1);

namespace App\DTOs\BinLocation;

final readonly class CreateBinLocationData
{
    public function __construct(
        public int $warehouseId,
        public ?int $warehouseZoneId,
        public ?string $zone,
        public ?string $aisle,
        public ?string $shelf,
        public ?int $capacityLimit,
    ) {}

    /**
     * @param  array{
     *     warehouse_zone_id?: int|null,
     *     zone?: string|null,
     *     aisle?: string|null,
     *     shelf?: string|null,
     *     capacity_limit?: int|null
     * }  $row
     */
    public static function fromArray(int $warehouseId, array $row): self
    {
        return new self(
            warehouseId: $warehouseId,
            warehouseZoneId: isset($row['warehouse_zone_id']) ? (int) $row['warehouse_zone_id'] : null,
            zone: $row['zone'] ?? null,
            aisle: $row['aisle'] ?? null,
            shelf: $row['shelf'] ?? null,
            capacityLimit: isset($row['capacity_limit']) ? (int) $row['capacity_limit'] : null,
        );
    }
}
