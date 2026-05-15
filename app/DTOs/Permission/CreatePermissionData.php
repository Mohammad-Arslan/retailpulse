<?php

declare(strict_types=1);

namespace App\DTOs\Permission;

use App\Http\Requests\Admin\StorePermissionRequest;

final readonly class CreatePermissionData
{
    public function __construct(
        public string $name,
        public ?string $group,
        public ?string $description,
    ) {}

    public static function fromRequest(StorePermissionRequest $request): self
    {
        return new self(
            name: $request->validated('name'),
            group: $request->validated('group'),
            description: $request->validated('description'),
        );
    }
}
