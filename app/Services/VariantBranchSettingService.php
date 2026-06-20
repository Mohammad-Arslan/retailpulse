<?php

declare(strict_types=1);

namespace App\Services;

use App\Models\VariantBranchSetting;

final class VariantBranchSettingService
{
    public function upsert(
        int $branchId,
        int $variantId,
        ?int $reorderPoint,
        ?int $safetyStockQty,
    ): VariantBranchSetting {
        if ($reorderPoint === null && $safetyStockQty === null) {
            VariantBranchSetting::query()
                ->where('branch_id', $branchId)
                ->where('product_variant_id', $variantId)
                ->delete();

            return new VariantBranchSetting([
                'branch_id' => $branchId,
                'product_variant_id' => $variantId,
            ]);
        }

        return VariantBranchSetting::query()->updateOrCreate(
            [
                'branch_id' => $branchId,
                'product_variant_id' => $variantId,
            ],
            [
                'reorder_point' => $reorderPoint,
                'safety_stock_qty' => $safetyStockQty,
            ],
        );
    }
}
