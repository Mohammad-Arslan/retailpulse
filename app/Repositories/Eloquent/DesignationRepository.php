<?php

declare(strict_types=1);

namespace App\Repositories\Eloquent;

use App\Models\Designation;
use App\Repositories\Contracts\DesignationRepositoryInterface;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Support\Collection;

final class DesignationRepository implements DesignationRepositoryInterface
{
    /**
     * @param  array<string, mixed>  $filters
     */
    public function paginate(array $filters, int $perPage): LengthAwarePaginator
    {
        $sort = in_array($filters['sort'] ?? '', ['name', 'code', 'status'], true)
            ? $filters['sort']
            : 'name';
        $direction = ($filters['direction'] ?? 'asc') === 'desc' ? 'desc' : 'asc';

        return Designation::query()
            ->with(['legalEntity:id,legal_name', 'defaultGrade:id,name,code'])
            ->when($filters['search'] ?? null, function ($q, string $search): void {
                $q->where(function ($inner) use ($search): void {
                    $inner->where('name', 'like', "%{$search}%")
                        ->orWhere('code', 'like', "%{$search}%");
                });
            })
            ->when($filters['status'] ?? null, fn ($q, string $status) => $q->where('status', $status))
            ->when($filters['legal_entity_id'] ?? null, fn ($q, $id) => $q->where('legal_entity_id', (int) $id))
            ->orderBy($sort, $direction)
            ->paginate($perPage)
            ->withQueryString();
    }

    public function activeForSelect(): Collection
    {
        return Designation::query()
            ->where('status', 'active')
            ->orderBy('name')
            ->get(['id', 'code', 'name', 'legal_entity_id']);
    }

    public function create(array $attributes): Designation
    {
        return Designation::query()->create($attributes);
    }

    public function update(Designation $designation, array $attributes): Designation
    {
        $designation->update($attributes);

        return $designation->fresh(['legalEntity', 'defaultGrade']) ?? $designation;
    }
}
