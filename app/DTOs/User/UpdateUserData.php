<?php

declare(strict_types=1);

namespace App\DTOs\User;

use App\Http\Requests\Admin\UpdateUserRequest;

final readonly class UpdateUserData
{
    /**
     * @param  list<string>|null  $roleNames
     */
    public function __construct(
        public string $name,
        public string $email,
        public ?string $password,
        public ?string $phone,
        public bool $isActive,
        public ?array $roleNames,
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
        );
    }
}
