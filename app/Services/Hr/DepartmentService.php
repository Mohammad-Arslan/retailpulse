<?php

declare(strict_types=1);

namespace App\Services\Hr;

use App\DTOs\Hr\CreateDepartmentData;
use App\DTOs\Hr\UpdateDepartmentData;
use App\Models\CostCentre;
use App\Models\Department;
use App\Models\Employee;
use App\Models\OrganizationEntity;
use App\Repositories\Contracts\DepartmentRepositoryInterface;
use App\Services\Accounting\DocumentNumberService;
use App\Services\Hr\Concerns\GeneratesHrMasterCodes;
use App\Support\DepartmentPresenter;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

final class DepartmentService
{
    use GeneratesHrMasterCodes;

    private const CODE_TYPE = 'department';

    private const CODE_PREFIX = 'DEPT';

    public function __construct(
        private readonly DepartmentRepositoryInterface $departments,
        private readonly DocumentNumberService $documentNumberService,
    ) {}

    protected function documentNumbers(): DocumentNumberService
    {
        return $this->documentNumberService;
    }

    /**
     * @param  array<string, mixed>  $filters
     * @return array{
     *     departments: LengthAwarePaginator,
     *     filters: array<string, mixed>,
     *     legalEntities: Collection,
     *     parentOptions: list<array{id: int, code: string, name: string}>,
     *     costCentres: Collection,
     *     nextCode: string
     * }
     */
    public function indexPayload(array $filters, int $perPage): array
    {
        return [
            'departments' => DepartmentPresenter::paginated($this->paginate($filters, $perPage)),
            'filters' => $filters,
            'legalEntities' => OrganizationEntity::query()
                ->where('status', 'active')
                ->orderBy('legal_name')
                ->get(['id', 'legal_name']),
            'parentOptions' => DepartmentPresenter::selectOptions($this->activeForSelect()),
            'costCentres' => CostCentre::query()
                ->where('status', 'active')
                ->orderBy('name')
                ->get(['id', 'code', 'name']),
            'employees' => Employee::query()
                ->where('status', 'active')
                ->orderBy('first_name')
                ->get(['id', 'first_name', 'last_name', 'employee_code', 'department_id']),
            'nextCode' => $this->peekMasterCode(self::CODE_TYPE, self::CODE_PREFIX),
        ];
    }

    /**
     * @param  array<string, mixed>  $filters
     */
    public function paginate(array $filters, int $perPage): LengthAwarePaginator
    {
        return $this->departments->paginate($filters, $perPage);
    }

    public function activeForSelect(?int $legalEntityId = null): Collection
    {
        return $this->departments->activeForSelect($legalEntityId);
    }

    public function create(CreateDepartmentData $data): Department
    {
        $attributes = $data->toArray();

        if ($attributes['parent_id'] !== null) {
            $this->assertNoCycle(null, (int) $attributes['parent_id']);
        }

        return DB::transaction(function () use ($attributes): Department {
            $attributes['code'] = $this->nextMasterCode(self::CODE_TYPE, self::CODE_PREFIX);

            return $this->departments->create($attributes);
        });
    }

    public function update(Department $department, UpdateDepartmentData $data): Department
    {
        $attributes = $data->toArray();

        if (array_key_exists('parent_id', $attributes) && $attributes['parent_id'] !== null) {
            $this->assertNoCycle($department->id, (int) $attributes['parent_id']);
        }

        if (($attributes['status'] ?? $department->status) === 'inactive' && $department->status !== 'inactive') {
            $this->assertCanDeactivate($department);
        }

        return $this->departments->update($department, $attributes);
    }

    public function assertNoCycle(?int $departmentId, int $parentId): void
    {
        if ($departmentId !== null && $departmentId === $parentId) {
            throw ValidationException::withMessages([
                'parent_id' => __('A department cannot be its own parent.'),
            ]);
        }

        $ancestorId = $parentId;
        while ($ancestorId !== null) {
            if ($departmentId !== null && $ancestorId === $departmentId) {
                throw ValidationException::withMessages([
                    'parent_id' => __('This parent would create a circular department hierarchy.'),
                ]);
            }

            $ancestorId = $this->departments->parentIdOf($ancestorId);
        }
    }

    public function assertCanDeactivate(Department $department): void
    {
        if ($this->departments->hasActiveEmployees($department)) {
            throw ValidationException::withMessages([
                'status' => __('Cannot deactivate a department with active employees assigned.'),
            ]);
        }
    }
}
