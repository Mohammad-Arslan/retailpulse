<?php

declare(strict_types=1);

namespace App\Services\Accounting;

use App\Enums\FixedAssetStatus;
use App\Models\FixedAsset;
use Carbon\Carbon;
use DomainException;
use Illuminate\Support\Facades\DB;

final class AssetDisposalService
{
    public function __construct(
        private readonly AccountingEventService $accountingEvents,
    ) {}

    /**
     * Dispose of a fixed asset and post removal journals including gain/loss.
     */
    public function dispose(FixedAsset $asset, Carbon $disposalDate, float $proceedsAmount, int $userId = 0): FixedAsset
    {
        if ($asset->status !== FixedAssetStatus::Active) {
            throw new DomainException(__('Only active assets can be disposed.'));
        }

        $nbv = $asset->netBookValue();
        $gainLoss = round($proceedsAmount - $nbv, 2);

        return DB::transaction(function () use ($asset, $disposalDate, $proceedsAmount, $gainLoss, $nbv, $userId) {
            $payload = [
                'date' => $disposalDate->toDateString(),
                'branch_id' => $asset->branch_id,
                'fixed_asset_id' => $asset->id,
                'gross_amount' => (float) $asset->acquisition_cost,
                'settlement_amount' => $proceedsAmount,
                'depreciation_amount' => (float) $asset->accumulated_depreciation,
                'inventory_cost' => $nbv,
                'description' => __('Asset disposal — :code', ['code' => $asset->asset_code]),
                'source_number' => $asset->asset_code,
            ];

            if ($gainLoss > 0) {
                $payload['custom_amount'] = $gainLoss;
            }

            $this->accountingEvents->process(
                'asset.disposed',
                FixedAsset::class,
                $asset->id,
                $payload,
                $userId > 0 ? $userId : (int) ($asset->custodian_user_id ?? 1),
            );

            $asset->update([
                'status' => FixedAssetStatus::Disposed,
                'accumulated_depreciation' => (float) $asset->acquisition_cost - (float) $asset->salvage_value,
            ]);

            return $asset->fresh();
        });
    }
}
