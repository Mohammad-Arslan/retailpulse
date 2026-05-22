<?php

declare(strict_types=1);

namespace App\DTOs\Unit;

use App\Http\Requests\Admin\UpdateUnitRequest;

final readonly class UpdateUnitData
{
    public function __construct(
        public string $name,
        public bool $isActive,
    ) {}

    public static function fromRequest(UpdateUnitRequest $request): self
    {
        return new self(
            name: $request->validated('name'),
            isActive: $request->boolean('is_active', true),
        );
    }
}
