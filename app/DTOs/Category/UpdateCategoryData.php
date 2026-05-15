<?php

declare(strict_types=1);

namespace App\DTOs\Category;

use App\Http\Requests\Admin\UpdateCategoryRequest;

final readonly class UpdateCategoryData
{
    public function __construct(
        public string $name,
        public ?int $parentId,
        public ?string $description,
        public int $sortOrder,
        public bool $isActive,
    ) {}

    public static function fromRequest(UpdateCategoryRequest $request): self
    {
        return new self(
            name: $request->validated('name'),
            parentId: $request->validated('parent_id'),
            description: $request->validated('description'),
            sortOrder: (int) $request->validated('sort_order', 0),
            isActive: $request->boolean('is_active', true),
        );
    }
}
