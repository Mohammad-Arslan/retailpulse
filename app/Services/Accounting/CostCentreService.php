<?php

declare(strict_types=1);

namespace App\Services\Accounting;

use App\DTOs\Accounting\CreateCostCentreData;
use App\DTOs\Accounting\UpdateCostCentreData;
use App\Models\CostCentre;
use App\Models\OrganizationEntity;
use App\Repositories\Contracts\BranchRepositoryInterface;
use App\Repositories\Contracts\CostCentreRepositoryInterface;
use App\Support\CostCentrePresenter;
use DomainException;

final class CostCentreService
{
    public function __construct(
        private readonly CostCentreRepositoryInterface $costCentreRepository,
        private readonly BranchRepositoryInterface $branchRepository,
    ) {}

    /**
     * @return array{costCentres: list<array<string, mixed>>, parentOptions: list<array{id: int, code: string, name: string}>, branches: mixed, legalEntities: mixed}
     */
    public function indexPayload(): array
    {
        $centres = $this->costCentreRepository->allOrderedWithRelations();

        return [
            'costCentres' => CostCentrePresenter::tree($centres),
            'parentOptions' => CostCentrePresenter::parentOptions($centres),
            'branches' => $this->branchRepository->allActive()->map->only(['id', 'name'])->values(),
            'legalEntities' => OrganizationEntity::query()->orderBy('legal_name')->get(['id', 'legal_name']),
        ];
    }

    public function create(CreateCostCentreData $data): CostCentre
    {
        return $this->costCentreRepository->create($data->toArray());
    }

    public function update(CostCentre $costCentre, UpdateCostCentreData $data): CostCentre
    {
        return $this->costCentreRepository->update($costCentre, $data->attributes);
    }

    public function delete(CostCentre $costCentre): void
    {
        if ($this->costCentreRepository->hasChildren($costCentre)) {
            throw new DomainException(__('Cannot delete a cost centre with children.'));
        }

        $this->costCentreRepository->delete($costCentre);
    }
}
