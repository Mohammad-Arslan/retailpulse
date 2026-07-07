<?php

declare(strict_types=1);

namespace App\Repositories\Eloquent;

use App\Models\CostCentre;
use App\Repositories\Contracts\CostCentreRepositoryInterface;
use Illuminate\Support\Collection;

final class CostCentreRepository implements CostCentreRepositoryInterface
{
    public function allOrderedWithRelations(): Collection
    {
        return CostCentre::query()
            ->with(['branch:id,name', 'parent:id,code,name'])
            ->orderBy('code')
            ->get();
    }

    public function activeOptions(): array
    {
        return CostCentre::query()
            ->where('status', 'active')
            ->orderBy('code')
            ->get(['id', 'code', 'name'])
            ->map(fn (CostCentre $centre) => [
                'id' => $centre->id,
                'code' => $centre->code,
                'name' => $centre->name,
            ])
            ->values()
            ->all();
    }

    public function create(array $attributes): CostCentre
    {
        return CostCentre::query()->create($attributes);
    }

    public function update(CostCentre $costCentre, array $attributes): CostCentre
    {
        $costCentre->update($attributes);

        return $costCentre->fresh(['branch', 'parent']) ?? $costCentre;
    }

    public function delete(CostCentre $costCentre): void
    {
        $costCentre->delete();
    }

    public function hasChildren(CostCentre $costCentre): bool
    {
        return $costCentre->children()->exists();
    }
}
