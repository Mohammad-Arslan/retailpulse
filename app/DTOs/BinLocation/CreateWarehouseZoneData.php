<?php

declare(strict_types=1);

namespace App\DTOs\BinLocation;

use App\Http\Requests\Admin\StoreWarehouseZoneRequest;

final readonly class CreateWarehouseZoneData
{
    public function __construct(
        public int $warehouseId,
        public string $name,
        public string $code,
    ) {}

    public static function fromRequest(StoreWarehouseZoneRequest $request): self
    {
        $validated = $request->validated();

        return new self(
            warehouseId: (int) $request->route('warehouse')->id,
            name: $validated['name'],
            code: strtoupper($validated['code']),
        );
    }
}
