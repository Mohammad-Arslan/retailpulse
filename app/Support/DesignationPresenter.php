<?php

declare(strict_types=1);

namespace App\Support;

use App\Models\Designation;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;

final class DesignationPresenter
{
    /**
     * @return array<string, mixed>
     */
    public static function listItem(Designation $designation): array
    {
        return [
            'id' => $designation->id,
            'code' => $designation->code,
            'name' => $designation->name,
            'legal_entity_id' => $designation->legal_entity_id,
            'legal_entity_name' => $designation->legalEntity?->legal_name,
            'default_grade_id' => $designation->default_grade_id,
            'default_grade_name' => $designation->defaultGrade?->name,
            'status' => $designation->status,
        ];
    }

    public static function paginated(LengthAwarePaginator $paginator): LengthAwarePaginator
    {
        return $paginator->through(fn (Designation $designation) => self::listItem($designation));
    }
}
