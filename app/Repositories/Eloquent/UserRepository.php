<?php

declare(strict_types=1);

namespace App\Repositories\Eloquent;

use App\Models\User;
use App\Repositories\Contracts\UserRepositoryInterface;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;

final class UserRepository implements UserRepositoryInterface
{
    public function findById(int $id): ?User
    {
        return User::query()->with('roles')->find($id);
    }

    public function findByEmail(string $email): ?User
    {
        return User::query()->where('email', $email)->first();
    }

    public function paginate(array $filters = [], int $perPage = 15): LengthAwarePaginator
    {
        $query = User::query()->with('roles');

        $sort = $filters['sort'] ?? 'created_at';
        $direction = strtolower((string) ($filters['direction'] ?? 'desc')) === 'asc' ? 'asc' : 'desc';
        $allowedSorts = ['name', 'email', 'created_at', 'is_active'];

        if (! in_array($sort, $allowedSorts, true)) {
            $sort = 'created_at';
        }

        $query->orderBy($sort, $direction);

        if (! empty($filters['search'])) {
            $search = $filters['search'];
            $query->where(function ($q) use ($search) {
                $q->where('name', 'like', "%{$search}%")
                    ->orWhere('email', 'like', "%{$search}%");
            });
        }

        if (isset($filters['is_active'])) {
            $query->where('is_active', filter_var($filters['is_active'], FILTER_VALIDATE_BOOLEAN));
        }

        return $query->paginate($perPage)->withQueryString();
    }

    public function create(array $attributes): User
    {
        return User::query()->create($attributes);
    }

    public function update(User $user, array $attributes): User
    {
        $user->update($attributes);

        return $user->fresh(['roles']);
    }

    public function delete(User $user): void
    {
        $user->delete();
    }
}
