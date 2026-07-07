<?php

declare(strict_types=1);

namespace App\Repositories\Contracts;

use App\Models\AssetCategory;
use App\Models\FixedAsset;
use Illuminate\Support\Collection;

interface FixedAssetRepositoryInterface
{
    /**
     * @return Collection<int, FixedAsset>
     */
    public function allWithRelations(): Collection;

    /**
     * @return Collection<int, AssetCategory>
     */
    public function activeCategories(): Collection;

    public function findCategoryById(int $id): ?AssetCategory;

    public function createAsset(array $attributes): FixedAsset;

    public function createCategory(array $attributes): AssetCategory;
}
