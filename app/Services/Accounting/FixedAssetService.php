<?php

declare(strict_types=1);

namespace App\Services\Accounting;

use App\DTOs\Accounting\CreateAssetCategoryData;
use App\DTOs\Accounting\CreateFixedAssetData;
use App\Models\FixedAsset;
use App\Models\OrganizationEntity;
use App\Repositories\Contracts\BranchRepositoryInterface;
use App\Repositories\Contracts\ChartOfAccountRepositoryInterface;
use App\Repositories\Contracts\FixedAssetRepositoryInterface;
use App\Support\FixedAssetPresenter;

final class FixedAssetService
{
    public function __construct(
        private readonly FixedAssetRepositoryInterface $fixedAssetRepository,
        private readonly BranchRepositoryInterface $branchRepository,
        private readonly ChartOfAccountRepositoryInterface $chartOfAccountRepository,
    ) {}

    /**
     * @return array<string, mixed>
     */
    public function indexPayload(): array
    {
        return [
            'assets' => $this->fixedAssetRepository->allWithRelations()
                ->map(fn (FixedAsset $asset) => FixedAssetPresenter::forList($asset))
                ->values(),
            'categories' => $this->fixedAssetRepository->activeCategories(),
            'branches' => $this->branchRepository->allActive()->map->only(['id', 'name'])->values(),
            'legalEntities' => OrganizationEntity::query()->orderBy('legal_name')->get(['id', 'legal_name']),
            'postableAccounts' => $this->chartOfAccountRepository->postableOptions(),
        ];
    }

    public function create(CreateFixedAssetData $data): FixedAsset
    {
        $category = $this->fixedAssetRepository->findCategoryById($data->categoryId);

        return $this->fixedAssetRepository->createAsset([
            'asset_code' => $data->assetCode,
            'name' => $data->name,
            'category_id' => $data->categoryId,
            'acquisition_cost' => $data->acquisitionCost,
            'acquisition_date' => $data->acquisitionDate,
            'useful_life_months' => $data->usefulLifeMonths,
            'salvage_value' => $data->salvageValue,
            'branch_id' => $data->branchId,
            'legal_entity_id' => $data->legalEntityId,
            'location' => $data->location,
            'depreciation_method' => $category?->depreciation_method ?? 'straight_line',
            'depreciation_start_date' => $data->acquisitionDate,
            'asset_account_id' => $category?->asset_account_id,
            'accumulated_depreciation_account_id' => $category?->accumulated_depreciation_account_id,
            'depreciation_expense_account_id' => $category?->depreciation_expense_account_id,
            'status' => 'active',
        ]);
    }

    public function createCategory(CreateAssetCategoryData $data): void
    {
        $this->fixedAssetRepository->createCategory([
            'name' => $data->name,
            'code' => $data->code,
            'default_useful_life_months' => $data->defaultUsefulLifeMonths,
            'asset_account_id' => $data->assetAccountId,
            'accumulated_depreciation_account_id' => $data->accumulatedDepreciationAccountId,
            'depreciation_expense_account_id' => $data->depreciationExpenseAccountId,
            'depreciation_method' => 'straight_line',
            'status' => 'active',
        ]);
    }
}
