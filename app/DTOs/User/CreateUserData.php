<?php

declare(strict_types=1);

namespace App\DTOs\User;

use App\Http\Requests\Admin\StoreUserRequest;

final readonly class CreateUserData
{
    /**
     * @param  list<string>  $roleNames
     */
    public function __construct(
        public string $name,
        public string $email,
        public string $password,
        public ?string $phone,
        public bool $isActive,
        public array $roleNames,
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
        );
    }
}
