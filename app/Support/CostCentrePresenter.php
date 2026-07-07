<?php

declare(strict_types=1);

namespace App\Support;

use App\Models\CostCentre;
use Illuminate\Support\Collection;

final class CostCentrePresenter
{
    /**
     * @param  Collection<int, CostCentre>  $centres
     * @return list<array<string, mixed>>
     */
    public static function tree(Collection $centres, ?int $parentId = null, int $depth = 0): array
    {
        return $centres
            ->where('parent_id', $parentId)
            ->values()
            ->map(fn (CostCentre $centre) => [
                'id' => $centre->id,
                'code' => $centre->code,
                'name' => $centre->name,
                'parent_id' => $centre->parent_id,
                'branch_id' => $centre->branch_id,
                'branch_name' => $centre->branch?->name,
                'legal_entity_id' => $centre->legal_entity_id,
                'status' => $centre->status,
                'depth' => $depth,
                'children' => self::tree($centres, $centre->id, $depth + 1),
            ])
            ->all();
    }

    /**
     * @return list<array{id: int, code: string, name: string}>
     */
    public static function parentOptions(Collection $centres): array
    {
        return $centres->map(fn (CostCentre $centre) => [
            'id' => $centre->id,
            'code' => $centre->code,
            'name' => $centre->name,
        ])->values()->all();
    }
}
