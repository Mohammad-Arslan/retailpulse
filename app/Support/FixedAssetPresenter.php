<?php

declare(strict_types=1);

namespace App\Support;

use App\Models\FixedAsset;

final class FixedAssetPresenter
{
    /**
     * @return array<string, mixed>
     */
    public static function forList(FixedAsset $asset): array
    {
        return [
            'id' => $asset->id,
            'asset_code' => $asset->asset_code,
            'name' => $asset->name,
            'category_name' => $asset->category?->name,
            'branch_name' => $asset->branch?->name,
            'acquisition_cost' => number_format((float) $asset->acquisition_cost, 2, '.', ''),
            'accumulated_depreciation' => number_format((float) $asset->accumulated_depreciation, 2, '.', ''),
            'net_book_value' => number_format($asset->netBookValue(), 2, '.', ''),
            'status' => $asset->status->value,
        ];
    }
}
