<?php

declare(strict_types=1);

namespace App\Repositories\Eloquent;

use App\Models\Branch;
use App\Repositories\Contracts\BranchRepositoryInterface;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Support\Collection;

final class BranchRepository implements BranchRepositoryInterface
{
    public function findById(int $id): ?Branch
    {
        return Branch::query()->with('warehouses')->find($id);
    }

    public function findByCode(string $code): ?Branch
    {
        return Branch::query()->where('code', $code)->first();
    }

    public function paginate(array $filters = [], ?array $accessibleBranchIds = null, int $perPage = 15): LengthAwarePaginator
    {
        $query = Branch::query()->withCount('warehouses');

        if ($accessibleBranchIds !== null) {
            $query->whereIn('id', $accessibleBranchIds);
        }

        $sort = $filters['sort'] ?? 'name';
        $direction = strtolower((string) ($filters['direction'] ?? 'asc')) === 'desc' ? 'desc' : 'asc';
        $allowedSorts = ['name', 'code', 'created_at', 'is_active'];

        if (! in_array($sort, $allowedSorts, true)) {
            $sort = 'name';
        }

        $query->orderBy($sort, $direction);

        if (! empty($filters['search'])) {
            $search = $filters['search'];
            $query->where(function ($q) use ($search) {
                $q->where('name', 'like', "%{$search}%")
                    ->orWhere('code', 'like', "%{$search}%");
            });
        }

        if (isset($filters['is_active'])) {
            $query->where('is_active', filter_var($filters['is_active'], FILTER_VALIDATE_BOOLEAN));
        }

        return $query->paginate($perPage)->withQueryString();
    }

    public function allActive(?array $accessibleBranchIds = null): Collection
    {
        $query = Branch::query()
            ->where('is_active', true)
            ->orderBy('name');

        if ($accessibleBranchIds !== null) {
            $query->whereIn('id', $accessibleBranchIds);
        }

        return $query->get(['id', 'name', 'code']);
    }

    public function create(array $attributes): Branch
    {
        return Branch::query()->create($attributes);
    }

    public function update(Branch $branch, array $attributes): Branch
    {
        $branch->update($attributes);

        return $branch->fresh(['warehouses']);
    }

    public function delete(Branch $branch): void
    {
        $branch->delete();
    }

    public function codeExists(string $code, ?int $exceptId = null): bool
    {
        return Branch::query()
            ->where('code', strtoupper($code))
            ->when($exceptId !== null, fn ($q) => $q->whereKeyNot($exceptId))
            ->exists();
    }
}
