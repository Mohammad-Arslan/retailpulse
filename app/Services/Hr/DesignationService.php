<?php

declare(strict_types=1);

namespace App\Services\Hr;

use App\DTOs\Hr\CreateDesignationData;
use App\DTOs\Hr\UpdateDesignationData;
use App\Models\Designation;
use App\Models\OrganizationEntity;
use App\Repositories\Contracts\DesignationRepositoryInterface;
use App\Services\Accounting\DocumentNumberService;
use App\Services\Hr\Concerns\GeneratesHrMasterCodes;
use App\Support\DesignationPresenter;
use App\Support\GradePresenter;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Support\Facades\DB;

final class DesignationService
{
    use GeneratesHrMasterCodes;

    private const CODE_TYPE = 'designation';

    private const CODE_PREFIX = 'DESIG';

    public function __construct(
        private readonly DesignationRepositoryInterface $designations,
        private readonly GradeService $grades,
        private readonly DocumentNumberService $documentNumberService,
    ) {}

    protected function documentNumbers(): DocumentNumberService
    {
        return $this->documentNumberService;
    }

    /**
     * @param  array<string, mixed>  $filters
     * @return array{
     *     designations: LengthAwarePaginator,
     *     filters: array<string, mixed>,
     *     legalEntities: \Illuminate\Support\Collection,
     *     grades: list<array{id: int, code: string, name: string}>,
     *     nextCode: string
     * }
     */
    public function indexPayload(array $filters, int $perPage): array
    {
        return [
            'designations' => DesignationPresenter::paginated($this->paginate($filters, $perPage)),
            'filters' => $filters,
            'legalEntities' => OrganizationEntity::query()
                ->where('status', 'active')
                ->orderBy('legal_name')
                ->get(['id', 'legal_name']),
            'grades' => GradePresenter::selectOptions($this->grades->activeForSelect()),
            'nextCode' => $this->peekMasterCode(self::CODE_TYPE, self::CODE_PREFIX),
        ];
    }

    /**
     * @param  array<string, mixed>  $filters
     */
    public function paginate(array $filters, int $perPage): LengthAwarePaginator
    {
        return $this->designations->paginate($filters, $perPage);
    }

    public function create(CreateDesignationData $data): Designation
    {
        return DB::transaction(function () use ($data): Designation {
            $attributes = $data->toArray();
            $attributes['code'] = $this->nextMasterCode(self::CODE_TYPE, self::CODE_PREFIX);

            return $this->designations->create($attributes);
        });
    }

    public function update(Designation $designation, UpdateDesignationData $data): Designation
    {
        return $this->designations->update($designation, $data->toArray());
    }
}
