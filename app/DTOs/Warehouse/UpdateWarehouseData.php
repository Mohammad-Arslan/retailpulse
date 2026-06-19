<?php

declare(strict_types=1);

namespace App\DTOs\Warehouse;

use App\Http\Requests\Admin\UpdateWarehouseRequest;

final readonly class UpdateWarehouseData
{
    public function __construct(
        public string $name,
        public bool $isDefault,
    ) {}

    public static function fromRequest(UpdateWarehouseRequest $request): self
    {
        return new self(
            name: $request->validated('name'),
            isDefault: $request->boolean('is_default'),
        );
    }
}
