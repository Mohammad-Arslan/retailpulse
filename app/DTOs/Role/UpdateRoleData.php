<?php

declare(strict_types=1);

namespace App\DTOs\Role;

use App\Http\Requests\Admin\UpdateRoleRequest;

final readonly class UpdateRoleData
{
    /**
     * @param  list<string>  $permissionNames
     */
    public function __construct(
        public string $name,
        public ?string $description,
        public array $permissionNames,
    ) {}

    public static function fromRequest(UpdateRoleRequest $request): self
    {
        return new self(
            name: $request->validated('name'),
            description: $request->validated('description'),
            permissionNames: $request->validated('permissions', []),
        );
    }
}
