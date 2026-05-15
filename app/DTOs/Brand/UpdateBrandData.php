<?php

declare(strict_types=1);

namespace App\DTOs\Brand;

use App\Http\Requests\Admin\UpdateBrandRequest;

final readonly class UpdateBrandData
{
    public function __construct(
        public string $name,
        public ?string $description,
        public bool $isActive,
    ) {}

    public static function fromRequest(UpdateBrandRequest $request): self
    {
        return new self(
            name: $request->validated('name'),
            description: $request->validated('description'),
            isActive: $request->boolean('is_active', true),
        );
    }
}
