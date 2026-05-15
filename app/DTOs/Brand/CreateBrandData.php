<?php

declare(strict_types=1);

namespace App\DTOs\Brand;

use App\Http\Requests\Admin\StoreBrandRequest;

final readonly class CreateBrandData
{
    public function __construct(
        public string $name,
        public ?string $description,
        public bool $isActive,
    ) {}

    public static function fromRequest(StoreBrandRequest $request): self
    {
        return new self(
            name: $request->validated('name'),
            description: $request->validated('description'),
            isActive: $request->boolean('is_active', true),
        );
    }
}
