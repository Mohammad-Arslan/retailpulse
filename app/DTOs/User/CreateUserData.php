<?php

declare(strict_types=1);

namespace App\DTOs\User;

use App\Http\Requests\Admin\StoreUserRequest;

final readonly class CreateUserData
{
    /**
     * @param  list<string>  $roleNames
     * @param  list<array{branch_id: int, is_primary: bool}>  $branchAssignments
     */
    public function __construct(
        public string $name,
        public string $email,
        public string $password,
        public ?string $phone,
        public bool $isActive,
        public array $roleNames,
        public array $branchAssignments,
        public ?string $posPin,
        public ?int $employeeId,
    ) {}

    public static function fromRequest(StoreUserRequest $request): self
    {
        return new self(
            name: $request->validated('name'),
            email: $request->validated('email'),
            password: $request->validated('password'),
            phone: $request->validated('phone'),
            isActive: $request->boolean('is_active', true),
            roleNames: $request->validated('roles', []),
            branchAssignments: BranchAssignmentData::fromInput(
                $request->validated('branches'),
            )->assignments,
            posPin: $request->validated('pos_pin'),
            employeeId: $request->validated('employee_id') !== null ? (int) $request->validated('employee_id') : null,
        );
    }
}
