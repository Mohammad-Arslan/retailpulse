<?php

declare(strict_types=1);

namespace App\DTOs\Unit;

use App\Http\Requests\Admin\StoreUnitRequest;

final readonly class CreateUnitData
{
    public function __construct(
        public string $name,
        public bool $isActive,
    ) {}

    public static function fromRequest(StoreUnitRequest $request): self
    {
        return new self(
            name: $request->validated('name'),
            isActive: $request->boolean('is_active', true),
        );
    }
}
