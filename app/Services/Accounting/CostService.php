<?php

declare(strict_types=1);

namespace App\Services\Accounting;

use App\Enums\InventoryValuationMethod;
use App\Models\GoodsReceivingNote;
use App\Models\GrnItem;
use App\Models\InventoryCostLayer;
use App\Models\LandedCostEntry;
use App\Models\PurchaseOrder;
use App\Models\PurchaseOrderItem;
use App\Models\SaleItem;
use DomainException;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

final class CostService
{
    public function __construct(
        private readonly FinancialSettingsService $financialSettings,
    ) {}

    public function createLayerFromGrnItem(
        GrnItem $grnItem,
        GoodsReceivingNote $grn,
        PurchaseOrderItem $poItem,
        ?string $batchNo = null,
    ): InventoryCostLayer {
        $poItem->loadMissing('purchaseOrder');
        $order = $poItem->purchaseOrder;
        $unitCost = $this->resolvePurchaseUnitCost($poItem, $order);

        return $this->createLayerOnReceive(
            productVariantId: (int) $grnItem->product_variant_id,
            warehouseId: (int) $grn->warehouse_id,
            qtyReceived: (float) $grnItem->qty_received,
            unitCost: $unitCost,
            sourceReferenceType: GrnItem::class,
            sourceReferenceId: (int) $grnItem->id,
            batchNo: $batchNo,
            receivedAt: $grn->received_at ?? now(),
        );
    }

    public function createLayerOnReceive(
        int $productVariantId,
        int $warehouseId,
        float $qtyReceived,
        float $unitCost,
        string $sourceReferenceType,
        int $sourceReferenceId,
        ?string $batchNo = null,
        ?\DateTimeInterface $receivedAt = null,
        float $landedCostAmount = 0,
        ?InventoryValuationMethod $valuationMethod = null,
    ): InventoryCostLayer {
        if ($qtyReceived <= 0) {
            throw ValidationException::withMessages([
                'qty_received' => __('Received quantity must be greater than zero.'),
            ]);
        }

        $settings = $this->financialSettings->get();
        $method = $valuationMethod ?? $settings->default_inventory_valuation_method ?? InventoryValuationMethod::Fifo;

        $effectiveUnitCost = $unitCost + ($qtyReceived > 0 ? $landedCostAmount / $qtyReceived : 0);

        return InventoryCostLayer::query()->create([
            'product_variant_id' => $productVariantId,
            'warehouse_id' => $warehouseId,
            'batch_no' => $batchNo,
            'received_at' => $receivedAt ?? now(),
            'qty_received' => $qtyReceived,
            'qty_remaining' => $qtyReceived,
            'unit_cost' => round($effectiveUnitCost, 4),
            'valuation_method' => $method,
            'landed_cost_amount' => round($landedCostAmount, 4),
            'source_reference_type' => $sourceReferenceType,
            'source_reference_id' => $sourceReferenceId,
            'status' => 'active',
        ]);
    }

    public function consumeOnSale(SaleItem $item): float
    {
        $item->loadMissing('sale');
        $sale = $item->sale;

        if ($sale === null) {
            throw new DomainException('Sale item is not linked to a sale.');
        }

        $warehouseId = (int) $sale->warehouse_id;
        $variantId = (int) $item->product_variant_id;
        $qtyNeeded = (float) $item->quantity;

        if ($qtyNeeded <= 0) {
            return 0.0;
        }

        $settings = $this->financialSettings->get();
        $method = $settings->default_inventory_valuation_method ?? InventoryValuationMethod::Fifo;

        return DB::transaction(function () use (
            $variantId,
            $warehouseId,
            $qtyNeeded,
            $method,
            $settings,
        ) {
            $totalCost = match ($method) {
                InventoryValuationMethod::Wac => $this->consumeWeightedAverage($variantId, $warehouseId, $qtyNeeded),
                InventoryValuationMethod::Fifo => $this->consumeFifoWithWacFallback(
                    $variantId,
                    $warehouseId,
                    $qtyNeeded,
                    (bool) $settings->allow_negative_inventory,
                ),
            };

            return round($totalCost, 2);
        });
    }

    public function restoreOnReturn(SaleItem $item, int $quantity, ?float $unitCost = null): InventoryCostLayer
    {
        $item->loadMissing('sale');
        $sale = $item->sale;

        if ($sale === null) {
            throw new DomainException('Sale item is not linked to a sale.');
        }

        if ($quantity <= 0) {
            throw ValidationException::withMessages([
                'quantity' => __('Return quantity must be greater than zero.'),
            ]);
        }

        $warehouseId = (int) $sale->warehouse_id;
        $variantId = (int) $item->product_variant_id;
        $restoreUnitCost = $unitCost ?? $this->weightedAverageUnitCost($variantId, $warehouseId);

        return $this->createLayerOnReceive(
            productVariantId: $variantId,
            warehouseId: $warehouseId,
            qtyReceived: (float) $quantity,
            unitCost: $restoreUnitCost,
            sourceReferenceType: SaleItem::class,
            sourceReferenceId: (int) $item->id,
            receivedAt: now(),
        );
    }

    public function applyLandedCost(LandedCostEntry $entry): void
    {
        $entry->loadMissing('allocations.grnItem');

        DB::transaction(function () use ($entry) {
            foreach ($entry->allocations as $allocation) {
                $layer = InventoryCostLayer::query()
                    ->where('source_reference_type', GrnItem::class)
                    ->where('source_reference_id', $allocation->grn_item_id)
                    ->where('status', 'active')
                    ->lockForUpdate()
                    ->first();

                if ($layer === null) {
                    continue;
                }

                $landedAmount = (float) $allocation->functional_amount;
                $qtyReceived = (float) $layer->qty_received;

                if ($qtyReceived <= 0) {
                    continue;
                }

                $perUnitLanded = $landedAmount / $qtyReceived;

                $layer->update([
                    'landed_cost_amount' => round((float) $layer->landed_cost_amount + $landedAmount, 4),
                    'unit_cost' => round((float) $layer->unit_cost + $perUnitLanded, 4),
                ]);
            }
        });
    }

    private function resolvePurchaseUnitCost(PurchaseOrderItem $poItem, ?PurchaseOrder $order): float
    {
        if ((float) $poItem->qty_ordered > 0 && (float) $poItem->functional_line_total > 0) {
            return round((float) $poItem->functional_line_total / (float) $poItem->qty_ordered, 4);
        }

        $exchangeRate = (float) ($order?->exchange_rate ?? 1);

        return round((float) $poItem->unit_price * $exchangeRate, 4);
    }

    private function consumeFifoWithWacFallback(
        int $variantId,
        int $warehouseId,
        float $qtyNeeded,
        bool $allowNegative,
    ): float {
        $remaining = $qtyNeeded;
        $totalCost = 0.0;

        $layers = InventoryCostLayer::query()
            ->where('product_variant_id', $variantId)
            ->where('warehouse_id', $warehouseId)
            ->where('status', 'active')
            ->where('qty_remaining', '>', 0)
            ->orderBy('received_at')
            ->orderBy('id')
            ->lockForUpdate()
            ->get();

        foreach ($layers as $layer) {
            if ($remaining <= 0) {
                break;
            }

            $layerQty = (float) $layer->qty_remaining;
            $take = min($remaining, $layerQty);
            $unitCost = (float) $layer->unit_cost;

            $totalCost += $take * $unitCost;
            $remaining -= $take;

            $newRemaining = round($layerQty - $take, 4);
            $layer->update([
                'qty_remaining' => $newRemaining,
                'status' => $newRemaining <= 0 ? 'depleted' : 'active',
            ]);
        }

        if ($remaining > 0) {
            $wacUnitCost = $this->weightedAverageUnitCost($variantId, $warehouseId);

            if ($wacUnitCost <= 0 && ! $allowNegative) {
                throw ValidationException::withMessages([
                    'inventory' => __('Insufficient inventory cost layers for this sale.'),
                ]);
            }

            $totalCost += $remaining * $wacUnitCost;
        }

        return $totalCost;
    }

    private function consumeWeightedAverage(int $variantId, int $warehouseId, float $qtyNeeded): float
    {
        $layers = InventoryCostLayer::query()
            ->where('product_variant_id', $variantId)
            ->where('warehouse_id', $warehouseId)
            ->where('status', 'active')
            ->where('qty_remaining', '>', 0)
            ->lockForUpdate()
            ->get();

        $totalQty = $layers->sum(fn (InventoryCostLayer $layer) => (float) $layer->qty_remaining);

        if ($totalQty <= 0) {
            $settings = $this->financialSettings->get();

            if (! $settings->allow_negative_inventory) {
                throw ValidationException::withMessages([
                    'inventory' => __('Insufficient inventory cost layers for this sale.'),
                ]);
            }

            return 0.0;
        }

        $wacUnitCost = $this->weightedAverageUnitCost($variantId, $warehouseId);
        $totalCost = $qtyNeeded * $wacUnitCost;
        $remaining = $qtyNeeded;

        foreach ($layers as $layer) {
            if ($remaining <= 0) {
                break;
            }

            $layerQty = (float) $layer->qty_remaining;
            $share = $layerQty / $totalQty;
            $take = min($remaining, round($qtyNeeded * $share, 4));

            if ($take <= 0) {
                continue;
            }

            $newRemaining = round($layerQty - $take, 4);
            $layer->update([
                'qty_remaining' => max(0, $newRemaining),
                'status' => $newRemaining <= 0 ? 'depleted' : 'active',
            ]);

            $remaining -= $take;
        }

        if ($remaining > 0 && $layers->isNotEmpty()) {
            $lastLayer = $layers->last();
            $lastQty = (float) $lastLayer->qty_remaining;
            $newRemaining = round($lastQty - $remaining, 4);
            $lastLayer->update([
                'qty_remaining' => max(0, $newRemaining),
                'status' => $newRemaining <= 0 ? 'depleted' : 'active',
            ]);
        }

        return $totalCost;
    }

    private function weightedAverageUnitCost(int $variantId, int $warehouseId): float
    {
        $layers = InventoryCostLayer::query()
            ->where('product_variant_id', $variantId)
            ->where('warehouse_id', $warehouseId)
            ->where('status', 'active')
            ->where('qty_remaining', '>', 0)
            ->get();

        $totalQty = $layers->sum(fn (InventoryCostLayer $layer) => (float) $layer->qty_remaining);

        if ($totalQty <= 0) {
            return 0.0;
        }

        $totalValue = $layers->sum(
            fn (InventoryCostLayer $layer) => (float) $layer->qty_remaining * (float) $layer->unit_cost,
        );

        return round($totalValue / $totalQty, 4);
    }
}
