<?php

declare(strict_types=1);

namespace App\DTOs\User;

use App\Http\Requests\Admin\UpdateUserRequest;

final readonly class UpdateUserData
{
    /**
     * @param  list<string>|null  $roleNames
     * @param  list<array{branch_id: int, is_primary: bool}>|null  $branchAssignments
     */
    public function __construct(
        public string $name,
        public string $email,
        public ?string $password,
        public ?string $phone,
        public bool $isActive,
        public ?array $roleNames,
        public ?array $branchAssignments,
        public ?string $posPin,
        public bool $clearPosPin,
    ) {}

    public static function fromRequest(UpdateUserRequest $request): self
    {
        return new self(
            name: $request->validated('name'),
            email: $request->validated('email'),
            password: $request->validated('password'),
            phone: $request->validated('phone'),
            isActive: $request->boolean('is_active', true),
            roleNames: $request->has('roles') ? $request->validated('roles', []) : null,
            branchAssignments: $request->has('branches')
                ? BranchAssignmentData::fromInput($request->validated('branches'))->assignments
                : null,
            posPin: $request->validated('pos_pin'),
            clearPosPin: $request->boolean('clear_pos_pin', false),
        );
    }
}
