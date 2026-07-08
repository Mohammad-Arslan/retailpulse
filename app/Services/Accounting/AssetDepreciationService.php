<?php

declare(strict_types=1);

namespace App\Services\Accounting;

use App\Enums\FixedAssetStatus;
use App\Models\FixedAsset;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

final class AssetDepreciationService
{
    public function __construct(
        private readonly AccountingEventService $accountingEvents,
    ) {}

    /**
     * @return Collection<int, FixedAsset>
     */
    public function processMonthly(?string $asOfDate = null): Collection
    {
        $asOf = $asOfDate ?? now()->toDateString();

        $assets = FixedAsset::query()
            ->where('status', FixedAssetStatus::Active)
            ->where(function ($q) use ($asOf) {
                $q->whereNull('last_depreciation_date')
                    ->orWhereDate('last_depreciation_date', '<', date('Y-m-01', strtotime($asOf)));
            })
            ->get();

        $processed = collect();

        foreach ($assets as $asset) {
            $depreciation = $this->depreciateAsset($asset, $asOf);

            if ($depreciation > 0) {
                $processed->push($asset->fresh());
            }
        }

        return $processed;
    }

    public function depreciateAsset(FixedAsset $asset, ?string $asOfDate = null, ?string $idempotencyPeriod = null): float
    {
        if ($asset->status !== FixedAssetStatus::Active) {
            return 0.0;
        }

        $monthly = $asset->monthlyDepreciation();
        $remaining = $asset->netBookValue() - (float) $asset->salvage_value;

        if ($monthly <= 0 || $remaining <= 0) {
            return 0.0;
        }

        $amount = min($monthly, $remaining);
        $effectiveDate = $asOfDate ?? now()->toDateString();
        $periodKey = $idempotencyPeriod ?? date('Y-m', strtotime($effectiveDate));

        return DB::transaction(function () use ($asset, $amount, $effectiveDate, $periodKey) {
            $asset->update([
                'accumulated_depreciation' => (float) $asset->accumulated_depreciation + $amount,
                'last_depreciation_date' => $effectiveDate,
            ]);

            $this->accountingEvents->process(
                'asset.depreciation_due',
                FixedAsset::class,
                $asset->id,
                [
                    'date' => $effectiveDate,
                    'branch_id' => $asset->branch_id,
                    'fixed_asset_id' => $asset->id,
                    'depreciation_amount' => $amount,
                    'settlement_amount' => $amount,
                    'description' => "Depreciation — {$asset->asset_code}",
                    'source_number' => $asset->asset_code,
                ],
                (int) ($asset->custodian_user_id ?? 1),
                $periodKey,
            );

            return $amount;
        });
    }
}
