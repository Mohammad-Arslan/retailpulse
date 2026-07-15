<?php

declare(strict_types=1);

namespace App\Repositories\Eloquent;

use App\Models\Grade;
use App\Repositories\Contracts\GradeRepositoryInterface;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Support\Collection;

final class GradeRepository implements GradeRepositoryInterface
{
    /**
     * @param  array<string, mixed>  $filters
     */
    public function paginate(array $filters, int $perPage): LengthAwarePaginator
    {
        $sort = in_array($filters['sort'] ?? '', ['name', 'code', 'rank', 'status'], true)
            ? $filters['sort']
            : 'rank';
        $direction = ($filters['direction'] ?? 'asc') === 'desc' ? 'desc' : 'asc';

        return Grade::query()
            ->with('legalEntity:id,legal_name')
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
        return Grade::query()
            ->where('status', 'active')
            ->orderBy('rank')
            ->orderBy('name')
            ->get(['id', 'code', 'name', 'legal_entity_id']);
    }

    public function findOverlapping(string $code, mixed $legalEntityId, ?string $from, ?string $to, ?int $excludeId = null): Collection
    {
        return Grade::query()
            ->where('code', $code)
            ->when($legalEntityId === null, fn ($q) => $q->whereNull('legal_entity_id'))
            ->when($legalEntityId !== null, fn ($q) => $q->where('legal_entity_id', $legalEntityId))
            ->when($excludeId !== null, fn ($q) => $q->where('id', '!=', $excludeId))
            ->get();
    }

    public function create(array $attributes): Grade
    {
        return Grade::query()->create($attributes);
    }

    public function update(Grade $grade, array $attributes): Grade
    {
        $grade->update($attributes);

        return $grade->fresh(['legalEntity']) ?? $grade;
    }
}
