<?php

declare(strict_types=1);

namespace App\DTOs\Warehouse;

use App\Enums\WarehouseType;
use App\Http\Requests\Admin\UpdateWarehouseRequest;

final readonly class UpdateWarehouseData
{
    public function __construct(
        public string $name,
        public WarehouseType $type,
        public bool $isDefault,
    ) {}

    public static function fromRequest(UpdateWarehouseRequest $request): self
    {
        return new self(
            name: $request->validated('name'),
            type: WarehouseType::from($request->validated('type')),
            isDefault: $request->boolean('is_default'),
        );
    }
}
