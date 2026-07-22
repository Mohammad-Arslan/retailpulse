<?php

declare(strict_types=1);

namespace App\DTOs\BinLocation;

use App\Http\Requests\Admin\UpdateBinLocationRequest;

final readonly class UpdateBinLocationData
{
    public function __construct(
        public ?int $warehouseZoneId,
        public ?string $zone,
        public ?string $aisle,
        public ?string $shelf,
        public ?int $capacityLimit,
        public bool $isActive,
    ) {}

    public static function fromRequest(UpdateBinLocationRequest $request): self
    {
        $validated = $request->validated();

        return new self(
            warehouseZoneId: isset($validated['warehouse_zone_id']) ? (int) $validated['warehouse_zone_id'] : null,
            zone: $validated['zone'] ?? null,
            aisle: $validated['aisle'] ?? null,
            shelf: $validated['shelf'] ?? null,
            capacityLimit: isset($validated['capacity_limit']) ? (int) $validated['capacity_limit'] : null,
            isActive: (bool) ($validated['is_active'] ?? true),
        );
    }
}
