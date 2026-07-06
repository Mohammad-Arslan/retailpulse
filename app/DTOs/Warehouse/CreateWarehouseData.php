<?php

declare(strict_types=1);

namespace App\DTOs\Warehouse;

use App\Enums\WarehouseType;
use App\Http\Requests\Admin\StoreWarehouseRequest;

final readonly class CreateWarehouseData
{
    public function __construct(
        public int $branchId,
        public string $name,
        public WarehouseType $type,
        public bool $isDefault,
    ) {}

    public static function fromRequest(StoreWarehouseRequest $request): self
    {
        return new self(
            branchId: (int) $request->validated('branch_id'),
            name: $request->validated('name'),
            type: WarehouseType::from($request->validated('type')),
            isDefault: $request->boolean('is_default'),
        );
    }
}
