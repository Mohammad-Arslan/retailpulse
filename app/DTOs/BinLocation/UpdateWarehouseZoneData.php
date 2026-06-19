<?php

declare(strict_types=1);

namespace App\DTOs\BinLocation;

use App\Http\Requests\Admin\UpdateWarehouseZoneRequest;

final readonly class UpdateWarehouseZoneData
{
    public function __construct(
        public string $name,
        public string $code,
        public bool $isActive,
    ) {}

    public static function fromRequest(UpdateWarehouseZoneRequest $request): self
    {
        $validated = $request->validated();

        return new self(
            name: $validated['name'],
            code: strtoupper($validated['code']),
            isActive: (bool) ($validated['is_active'] ?? true),
        );
    }
}
