<?php

declare(strict_types=1);

namespace App\Repositories\Eloquent;

use App\Models\AssetCategory;
use App\Models\FixedAsset;
use App\Repositories\Contracts\FixedAssetRepositoryInterface;
use Illuminate\Support\Collection;

final class FixedAssetRepository implements FixedAssetRepositoryInterface
{
    public function allWithRelations(): Collection
    {
        return FixedAsset::query()
            ->with(['category:id,name', 'branch:id,name'])
            ->orderBy('asset_code')
            ->get();
    }

    public function activeCategories(): Collection
    {
        return AssetCategory::query()
            ->where('status', 'active')
            ->orderBy('name')
            ->get(['id', 'name', 'code']);
    }

    public function findCategoryById(int $id): ?AssetCategory
    {
        return AssetCategory::query()->find($id);
    }

    public function createAsset(array $attributes): FixedAsset
    {
        return FixedAsset::query()->create($attributes);
    }

    public function createCategory(array $attributes): AssetCategory
    {
        return AssetCategory::query()->create($attributes);
    }
}
