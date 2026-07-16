<?php

declare(strict_types=1);

namespace App\Support;

use App\Models\Department;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Support\Collection;

final class DepartmentPresenter
{
    /**
     * @return array<string, mixed>
     */
    public static function listItem(Department $department): array
    {
        return [
            'id' => $department->id,
            'code' => $department->code,
            'name' => $department->name,
            'parent_id' => $department->parent_id,
            'parent_name' => $department->parent?->name,
            'head_employee_id' => $department->head_employee_id,
            'head_employee_name' => $department->head?->fullName(),
            'legal_entity_id' => $department->legal_entity_id,
            'legal_entity_name' => $department->legalEntity?->legal_name,
            'cost_centre_id' => $department->cost_centre_id,
            'status' => $department->status,
        ];
    }

    public static function paginated(LengthAwarePaginator $paginator): LengthAwarePaginator
    {
        return $paginator->through(fn (Department $department) => self::listItem($department));
    }

    /**
     * @param  Collection<int, Department>  $departments
     * @return list<array{id: int, code: string, name: string}>
     */
    public static function selectOptions(Collection $departments): array
    {
        return $departments
            ->map(static fn (Department $d) => [
                'id' => $d->id,
                'code' => $d->code,
                'name' => $d->name,
            ])
            ->values()
            ->all();
    }
}
