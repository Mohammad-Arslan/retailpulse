<?php

declare(strict_types=1);

namespace App\Support;

use App\Models\Grade;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Support\Collection;

final class GradePresenter
{
    /**
     * @return array<string, mixed>
     */
    public static function listItem(Grade $grade): array
    {
        return [
            'id' => $grade->id,
            'code' => $grade->code,
            'name' => $grade->name,
            'rank' => $grade->rank,
            'legal_entity_id' => $grade->legal_entity_id,
            'legal_entity_name' => $grade->legalEntity?->legal_name,
            'currency_code' => $grade->currency_code,
            'min_amount' => $grade->min_amount,
            'mid_amount' => $grade->mid_amount,
            'max_amount' => $grade->max_amount,
            'enforce_salary_band' => $grade->enforce_salary_band,
            'effective_from' => $grade->effective_from?->toDateString(),
            'effective_to' => $grade->effective_to?->toDateString(),
            'status' => $grade->status,
        ];
    }

    public static function paginated(LengthAwarePaginator $paginator): LengthAwarePaginator
    {
        return $paginator->through(fn (Grade $grade) => self::listItem($grade));
    }

    /**
     * @param  Collection<int, Grade>  $grades
     * @return list<array{id: int, code: string, name: string}>
     */
    public static function selectOptions(Collection $grades): array
    {
        return $grades
            ->map(static fn (Grade $g) => [
                'id' => $g->id,
                'code' => $g->code,
                'name' => $g->name,
            ])
            ->values()
            ->all();
    }
}
