<?php

declare(strict_types=1);

namespace App\DTOs\BinLocation;

use App\Http\Requests\Admin\StoreBinLocationRequest;

final readonly class CreateBinLocationData
{
    public function __construct(
        public int $warehouseId,
        public ?int $warehouseZoneId,
        public ?string $zone,
        public ?string $aisle,
        public ?string $shelf,
        public string $binCode,
        public ?int $capacityLimit,
    ) {}

    public static function fromRequest(StoreBinLocationRequest $request): self
    {
        $validated = $request->validated();

        return new self(
            warehouseId: (int) $request->route('warehouse')->id,
            warehouseZoneId: isset($validated['warehouse_zone_id']) ? (int) $validated['warehouse_zone_id'] : null,
            zone: $validated['zone'] ?? null,
            aisle: $validated['aisle'] ?? null,
            shelf: $validated['shelf'] ?? null,
            binCode: strtoupper($validated['bin_code']),
            capacityLimit: isset($validated['capacity_limit']) ? (int) $validated['capacity_limit'] : null,
        );
    }
}
