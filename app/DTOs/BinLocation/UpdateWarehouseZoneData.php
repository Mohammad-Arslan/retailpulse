<?php

declare(strict_types=1);

namespace App\DTOs\BinLocation;

use App\Http\Requests\Admin\UpdateWarehouseZoneRequest;

final readonly class UpdateWarehouseZoneData
{
    public function __construct(
        public string $name,
        public ?int $capacityLimit,
        public bool $isActive,
    ) {}

    public static function fromRequest(UpdateWarehouseZoneRequest $request): self
    {
        $validated = $request->validated();

        return new self(
            name: $validated['name'],
            capacityLimit: isset($validated['capacity_limit']) ? (int) $validated['capacity_limit'] : null,
            isActive: (bool) ($validated['is_active'] ?? true),
        );
    }
}
