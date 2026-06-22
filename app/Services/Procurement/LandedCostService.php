<?php

declare(strict_types=1);

namespace App\Services\Procurement;

use App\Enums\LandedCostAllocationMethod;
use App\Models\GoodsReceivingNote;
use App\Models\LandedCostEntry;
use Illuminate\Support\Facades\DB;

final class LandedCostService
{
    /**
     * @param  list<array<string, mixed>>  $manualAllocations
     */
    public function allocate(
        GoodsReceivingNote $grn,
        string $chargeType,
        float $amount,
        string $currencyCode,
        float $exchangeRate,
        LandedCostAllocationMethod $method,
        int $userId,
        ?string $description = null,
        array $manualAllocations = [],
    ): LandedCostEntry {
        $grn->load('items');

        return DB::transaction(function () use (
            $grn, $chargeType, $amount, $currencyCode, $exchangeRate,
            $method, $userId, $description, $manualAllocations
        ) {
            $entry = LandedCostEntry::query()->create([
                'grn_id' => $grn->id,
                'charge_type' => $chargeType,
                'description' => $description,
                'amount' => $amount,
                'currency_code' => $currencyCode,
                'exchange_rate' => $exchangeRate,
                'functional_amount' => round($amount * $exchangeRate, 2),
                'allocation_method' => $method,
                'created_by' => $userId,
                'updated_by' => $userId,
            ]);

            $allocations = match ($method) {
                LandedCostAllocationMethod::Manual => $manualAllocations,
                LandedCostAllocationMethod::Quantity => $this->allocateByQuantity($grn, $amount, $exchangeRate),
                LandedCostAllocationMethod::Value => $this->allocateByValue($grn, $amount, $exchangeRate),
                LandedCostAllocationMethod::Weight => $this->allocateByQuantity($grn, $amount, $exchangeRate),
            };

            foreach ($allocations as $allocation) {
                $entry->allocations()->create($allocation);
            }

            return $entry->fresh(['allocations']) ?? $entry;
        });
    }

    /**
     * @return list<array<string, mixed>>
     */
    private function allocateByQuantity(GoodsReceivingNote $grn, float $amount, float $exchangeRate): array
    {
        $totalQty = $grn->items->sum('qty_received');

        if ($totalQty <= 0) {
            return [];
        }

        $allocations = [];

        foreach ($grn->items as $item) {
            $share = (float) $item->qty_received / (float) $totalQty;
            $allocated = round($amount * $share, 4);
            $allocations[] = [
                'grn_item_id' => $item->id,
                'allocated_amount' => $allocated,
                'functional_amount' => round($allocated * $exchangeRate, 4),
            ];
        }

        return $allocations;
    }

    /**
     * @return list<array<string, mixed>>
     */
    private function allocateByValue(GoodsReceivingNote $grn, float $amount, float $exchangeRate): array
    {
        $grn->load('items.purchaseOrderItem');
        $totalValue = $grn->items->sum(fn ($item) => (float) $item->qty_received * (float) ($item->purchaseOrderItem?->unit_price ?? 0));

        if ($totalValue <= 0) {
            return $this->allocateByQuantity($grn, $amount, $exchangeRate);
        }

        $allocations = [];

        foreach ($grn->items as $item) {
            $lineValue = (float) $item->qty_received * (float) ($item->purchaseOrderItem?->unit_price ?? 0);
            $share = $lineValue / $totalValue;
            $allocated = round($amount * $share, 4);
            $allocations[] = [
                'grn_item_id' => $item->id,
                'allocated_amount' => $allocated,
                'functional_amount' => round($allocated * $exchangeRate, 4),
            ];
        }

        return $allocations;
    }
}
