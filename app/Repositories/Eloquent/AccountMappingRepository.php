<?php

declare(strict_types=1);

namespace App\Repositories\Eloquent;

use App\Models\AccountMapping;
use App\Repositories\Contracts\AccountMappingRepositoryInterface;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;

final class AccountMappingRepository implements AccountMappingRepositoryInterface
{
    public function paginate(array $filters = [], int $perPage = 15): LengthAwarePaginator
    {
        $query = AccountMapping::query()
            ->with(['account:id,code,name', 'branch:id,name']);

        if (! empty($filters['search'])) {
            $search = (string) $filters['search'];
            $query->where(function ($q) use ($search) {
                $q->where('mapping_key', 'like', "%{$search}%")
                    ->orWhereHas('account', fn ($aq) => $aq->where('name', 'like', "%{$search}%")
                        ->orWhere('code', 'like', "%{$search}%"));
            });
        }

        if (! empty($filters['mapping_key'])) {
            $query->where('mapping_key', $filters['mapping_key']);
        }

        if (! empty($filters['status'])) {
            $query->where('status', $filters['status']);
        }

        $sort = $filters['sort'] ?? 'priority';
        $direction = ($filters['direction'] ?? 'asc') === 'desc' ? 'desc' : 'asc';
        $query->orderBy(
            in_array($sort, ['mapping_key', 'priority', 'status', 'created_at'], true) ? $sort : 'priority',
            $direction,
        );

        return $query->paginate($perPage)->withQueryString();
    }

    public function create(array $attributes): AccountMapping
    {
        return AccountMapping::query()->create($attributes);
    }

    public function update(AccountMapping $mapping, array $attributes): AccountMapping
    {
        $mapping->update($attributes);

        return $mapping->fresh(['account', 'branch']) ?? $mapping;
    }

    public function delete(AccountMapping $mapping): void
    {
        $mapping->delete();
    }
}
