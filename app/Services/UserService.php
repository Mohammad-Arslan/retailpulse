<?php

declare(strict_types=1);

namespace App\Services;

use App\DTOs\User\CreateUserData;
use App\DTOs\User\UpdateUserData;
use App\Models\User;
use App\Repositories\Contracts\UserRepositoryInterface;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;

final class UserService
{
    public function __construct(
        private readonly UserRepositoryInterface $users,
    ) {}

    public function create(CreateUserData $data): User
    {
        return DB::transaction(function () use ($data) {
            $user = $this->users->create([
                'name' => $data->name,
                'email' => $data->email,
                'password' => Hash::make($data->password),
                'phone' => $data->phone,
                'is_active' => $data->isActive,
            ]);

            if ($data->roleNames !== []) {
                $user->syncRoles($data->roleNames);
            }

            return $user->load('roles');
        });
    }

    public function update(User $user, UpdateUserData $data): User
    {
        return DB::transaction(function () use ($user, $data) {
            $attributes = [
                'name' => $data->name,
                'email' => $data->email,
                'phone' => $data->phone,
                'is_active' => $data->isActive,
            ];

            if ($data->password !== null && $data->password !== '') {
                $attributes['password'] = Hash::make($data->password);
            }

            $user = $this->users->update($user, $attributes);

            if ($data->roleNames !== null) {
                $user->syncRoles($data->roleNames);
            }

            return $user->load('roles');
        });
    }

    public function delete(User $user): void
    {
        DB::transaction(fn () => $this->users->delete($user));
    }

    public function recordSuccessfulLogin(User $user, string $ip): void
    {
        $this->users->update($user, [
            'last_login_at' => now(),
            'last_login_ip' => $ip,
            'failed_login_attempts' => 0,
            'locked_until' => null,
        ]);
    }

    public function recordFailedLogin(User $user): void
    {
        $attempts = $user->failed_login_attempts + 1;
        $attributes = ['failed_login_attempts' => $attempts];

        if ($attempts >= 5) {
            $attributes['locked_until'] = now()->addMinutes(15);
        }

        $this->users->update($user, $attributes);
    }
}
