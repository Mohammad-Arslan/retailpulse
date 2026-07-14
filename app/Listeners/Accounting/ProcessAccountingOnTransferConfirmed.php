<?php

declare(strict_types=1);

namespace App\Listeners\Accounting;

use App\Events\TransferConfirmed;
use App\Models\StockTransfer;
use App\Services\Accounting\AccountingEventService;
use App\Services\Accounting\CostService;

final class ProcessAccountingOnTransferConfirmed
{
    public function __construct(
        private readonly AccountingEventService $accountingEvents,
        private readonly CostService $costService,
    ) {}

    public function handle(TransferConfirmed $event): void
    {
        $transfer = $event->transfer->loadMissing(['items', 'fromWarehouse', 'toWarehouse']);

        $inventoryCost = 0.0;

        foreach ($transfer->items as $item) {
            $qty = (float) $item->qty_received;
            if ($qty <= 0) {
                continue;
            }

            $unitCost = (float) ($item->getAttribute('unit_cost') ?? 0);
            if ($unitCost <= 0) {
                $unitCost = $this->costService->averageUnitCost(
                    (int) $item->product_variant_id,
                    (int) $transfer->from_warehouse_id,
                );
            }

            $inventoryCost += round($qty * $unitCost, 2);
        }

        if ($inventoryCost <= 0) {
            return;
        }

        $userId = (int) ($transfer->received_by ?? $transfer->created_by ?? 0);

        $this->accountingEvents->process(
            'transfer.confirmed',
            StockTransfer::class,
            (int) $transfer->id,
            [
                'date' => $transfer->received_at?->toDateString() ?? now()->toDateString(),
                'branch_id' => $transfer->toWarehouse?->branch_id,
                'warehouse_id' => $transfer->to_warehouse_id,
                'from_warehouse_id' => $transfer->from_warehouse_id,
                'to_warehouse_id' => $transfer->to_warehouse_id,
                'inventory_cost' => $inventoryCost,
                'gross_amount' => $inventoryCost,
                'net_amount' => $inventoryCost,
                'source_module' => 'inventory',
                'source_number' => $transfer->reference_no,
                'description' => __('Stock transfer :ref received', ['ref' => $transfer->reference_no]),
                'user_id' => $userId > 0 ? $userId : null,
            ],
            $userId,
        );
    }
}
