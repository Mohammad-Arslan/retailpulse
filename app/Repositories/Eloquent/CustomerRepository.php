<?php

declare(strict_types=1);

namespace App\Repositories\Eloquent;

use App\Models\Customer;
use App\Repositories\Contracts\CustomerRepositoryInterface;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;

final class CustomerRepository implements CustomerRepositoryInterface
{
    public function paginate(array $filters = [], int $perPage = 15): LengthAwarePaginator
    {
        $query = Customer::query()
            ->with(['loyaltyTier', 'customerGroup']);

        $sort = $filters['sort'] ?? 'name';
        $direction = strtolower((string) ($filters['direction'] ?? 'asc')) === 'desc' ? 'desc' : 'asc';
        $allowedSorts = ['name', 'phone', 'email', 'created_at', 'is_active'];

        if (! in_array($sort, $allowedSorts, true)) {
            $sort = 'name';
        }

        $query->orderBy($sort, $direction);

        if (! empty($filters['search'])) {
            $search = $filters['search'];
            $query->where(function ($q) use ($search) {
                $q->where('name', 'like', "%{$search}%")
                    ->orWhere('phone', 'like', "%{$search}%")
                    ->orWhere('email', 'like', "%{$search}%");
            });
        }

        if (isset($filters['is_active'])) {
            $query->where('is_active', filter_var($filters['is_active'], FILTER_VALIDATE_BOOLEAN));
        }

        if (! empty($filters['loyalty_tier_id'])) {
            $query->where('loyalty_tier_id', (int) $filters['loyalty_tier_id']);
        }

        if (! empty($filters['customer_group_id'])) {
            $query->where('customer_group_id', (int) $filters['customer_group_id']);
        }

        return $query->paginate($perPage)->withQueryString();
    }

    public function findById(int $id): ?Customer
    {
        return Customer::query()
            ->with(['loyaltyTier', 'customerGroup', 'wallet'])
            ->find($id);
    }

    public function findByPhone(string $phone): ?Customer
    {
        return Customer::query()->where('phone', $phone)->first();
    }

    public function findByEmail(string $email): ?Customer
    {
        return Customer::query()->where('email', $email)->first();
    }

    public function create(array $attributes): Customer
    {
        return Customer::query()->create($attributes);
    }

    public function update(Customer $customer, array $attributes): Customer
    {
        $customer->update($attributes);

        return $customer->fresh(['loyaltyTier', 'customerGroup', 'wallet']) ?? $customer;
    }
}
