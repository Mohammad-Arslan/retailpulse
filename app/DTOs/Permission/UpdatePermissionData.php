<?php

declare(strict_types=1);

namespace App\DTOs\Permission;

use App\Http\Requests\Admin\UpdatePermissionRequest;

final readonly class UpdatePermissionData
{
    public function __construct(
        public string $name,
        public ?string $group,
        public ?string $description,
    ) {}

    public static function fromRequest(UpdatePermissionRequest $request): self
    {
        return new self(
            name: $request->validated('name'),
            group: $request->validated('group'),
            description: $request->validated('description'),
        );
    }
}
