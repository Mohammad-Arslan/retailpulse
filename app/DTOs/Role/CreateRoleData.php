<?php

declare(strict_types=1);

namespace App\DTOs\Role;

use App\Http\Requests\Admin\StoreRoleRequest;

final readonly class CreateRoleData
{
    /**
     * @param  list<string>  $permissionNames
     */
    public function __construct(
        public string $name,
        public ?string $description,
        public array $permissionNames,
    ) {}

    public static function fromRequest(StoreRoleRequest $request): self
    {
        return new self(
            name: $request->validated('name'),
            description: $request->validated('description'),
            permissionNames: $request->validated('permissions', []),
        );
    }
}
