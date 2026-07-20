<?php

declare(strict_types=1);

namespace App\Listeners\Accounting;

use App\Enums\StockMovementReason;
use App\Events\InventoryStockChanged;
use App\Models\SaleItem;
use App\Models\StockMovement;
use App\Services\Accounting\AccountingEventService;
use App\Services\Accounting\CostService;

final class ProcessAccountingOnInventoryMovement
{
    public function __construct(
        private readonly AccountingEventService $accountingEvents,
        private readonly CostService $costService,
    ) {}

    public function handle(InventoryStockChanged $event): void
    {
        if ($event->stockMovementId === null) {
            return;
        }

        $movement = StockMovement::query()->find($event->stockMovementId);

        if ($movement === null) {
            return;
        }

        $signedQtyDelta = (int) $movement->qty_delta;
        $qtyDelta = abs($signedQtyDelta);

        if ($qtyDelta === 0) {
            return;
        }

        $eventType = $this->resolveEventType($event->reason, $signedQtyDelta);

        if ($eventType === null) {
            return;
        }

        $inventory = $event->inventory->loadMissing('warehouse');

        $unitCost = $this->costService->averageUnitCost(
            (int) $inventory->product_variant_id,
            (int) $inventory->warehouse_id,
        );

        if (in_array($event->reason, [StockMovementReason::SaleReturn, StockMovementReason::ReturnCustomer], true)
            && $movement->reference_type === SaleItem::class
            && $movement->reference_id !== null
        ) {
            $saleItem = SaleItem::query()->find($movement->reference_id);

            if ($saleItem !== null) {
                $this->costService->restoreOnReturn(
                    $saleItem,
                    $qtyDelta,
                    $saleItem->cost_consumed !== null ? (float) $saleItem->cost_consumed / max(1, (int) $saleItem->quantity) : null,
                );
                $unitCost = $saleItem->cost_consumed !== null
                    ? (float) $saleItem->cost_consumed / max(1, (int) $saleItem->quantity)
                    : $unitCost;
            }
        }

        $inventoryCost = round($qtyDelta * $unitCost, 2);

        $this->accountingEvents->process(
            $eventType,
            StockMovement::class,
            (int) $movement->id,
            [
                'date' => $movement->created_at?->toDateString() ?? now()->toDateString(),
                'branch_id' => $inventory->warehouse?->branch_id,
                'warehouse_id' => $inventory->warehouse_id,
                'gross_amount' => $inventoryCost,
                'net_amount' => $inventoryCost,
                'inventory_cost' => $inventoryCost,
                'quantity' => $qtyDelta,
                'source_module' => 'inventory',
                'description' => __('Inventory movement :reason', ['reason' => $event->reason->value]),
                'user_id' => $movement->user_id,
            ],
            (int) ($movement->user_id ?? 0),
        );
    }

    private function resolveEventType(StockMovementReason $reason, int $signedQtyDelta): ?string
    {
        return match ($reason) {
            StockMovementReason::SaleReturn,
            StockMovementReason::ReturnCustomer => 'sale.returned',
            StockMovementReason::Adjustment,
            StockMovementReason::CycleCountAdjustment => $signedQtyDelta >= 0
                ? 'inventory.adjustment_gain'
                : 'inventory.adjustment_loss',
            StockMovementReason::Damaged,
            StockMovementReason::QuarantineScrap => 'stock.scrapped',
            default => null,
        };
    }
}
