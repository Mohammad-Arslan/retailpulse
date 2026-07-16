<?php

declare(strict_types=1);

namespace App\Services\Hr;

use App\Models\HrEmploymentType;
use App\Models\OrganizationEntity;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Support\Collection;

final class HrEmploymentTypeService
{
    /**
     * @param  array<string, mixed>  $filters
     * @return array<string, mixed>
     */
    public function indexPayload(array $filters, int $perPage): array
    {
        $paginator = $this->paginate($filters, $perPage);

        return [
            'employmentTypes' => $paginator->through(fn (HrEmploymentType $type): array => [
                'id' => $type->id,
                'code' => $type->code,
                'name' => $type->name,
                'status' => $type->status,
                'legal_entity_id' => $type->legal_entity_id,
                'legal_entity_name' => $type->legalEntity?->legal_name,
            ]),
            'filters' => $filters,
            'legalEntities' => OrganizationEntity::query()
                ->where('status', 'active')
                ->orderBy('legal_name')
                ->get(['id', 'legal_name']),
        ];
    }

    /**
     * @param  array<string, mixed>  $filters
     */
    public function paginate(array $filters, int $perPage): LengthAwarePaginator
    {
        $query = HrEmploymentType::query()->with('legalEntity:id,legal_name');

        if (! empty($filters['search'])) {
            $search = (string) $filters['search'];
            $query->where(function ($q) use ($search): void {
                $q->where('code', 'like', "%{$search}%")
                    ->orWhere('name', 'like', "%{$search}%");
            });
        }

        if (! empty($filters['status'])) {
            $query->where('status', (string) $filters['status']);
        }

        if (array_key_exists('legal_entity_id', $filters) && $filters['legal_entity_id'] !== '' && $filters['legal_entity_id'] !== null) {
            if ($filters['legal_entity_id'] === 'global') {
                $query->whereNull('legal_entity_id');
            } else {
                $query->where('legal_entity_id', (int) $filters['legal_entity_id']);
            }
        }

        $sort = in_array($filters['sort'] ?? '', ['code', 'name', 'status'], true)
            ? $filters['sort']
            : 'code';
        $direction = ($filters['direction'] ?? 'asc') === 'desc' ? 'desc' : 'asc';

        return $query->orderBy($sort, $direction)->paginate($perPage);
    }

    /**
     * Active codes for employee forms: global + entity-specific.
     *
     * @return list<array{code: string, name: string}>
     */
    public function optionsForEntity(?int $legalEntityId): array
    {
        $query = HrEmploymentType::query()
            ->where('status', 'active')
            ->where(function ($q) use ($legalEntityId): void {
                $q->whereNull('legal_entity_id');
                if ($legalEntityId !== null) {
                    $q->orWhere('legal_entity_id', $legalEntityId);
                }
            })
            ->orderBy('name');

        return $query->get(['code', 'name'])
            ->map(fn (HrEmploymentType $type): array => [
                'code' => $type->code,
                'name' => $type->name,
            ])
            ->values()
            ->all();
    }

    public function codesForEntity(?int $legalEntityId): Collection
    {
        return collect($this->optionsForEntity($legalEntityId))->pluck('code');
    }

    public function isValidCode(?int $legalEntityId, string $code): bool
    {
        return HrEmploymentType::query()
            ->where('code', $code)
            ->where('status', 'active')
            ->where(function ($q) use ($legalEntityId): void {
                $q->whereNull('legal_entity_id');
                if ($legalEntityId !== null) {
                    $q->orWhere('legal_entity_id', $legalEntityId);
                }
            })
            ->exists();
    }

    /**
     * @param  array<string, mixed>  $attributes
     */
    public function create(array $attributes): HrEmploymentType
    {
        return HrEmploymentType::query()->create($attributes);
    }

    /**
     * @param  array<string, mixed>  $attributes
     */
    public function update(HrEmploymentType $type, array $attributes): HrEmploymentType
    {
        $type->update($attributes);

        return $type->fresh(['legalEntity']) ?? $type;
    }
}
