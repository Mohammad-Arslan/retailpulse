<?php

declare(strict_types=1);

namespace App\Services\Accounting;

use App\Models\LandedCostEntry;
use App\Models\PurchaseReturn;
use App\Models\Supplier;
use App\Services\Procurement\Contracts\ProcurementPostingHook;

final class ProcurementAccountingHook implements ProcurementPostingHook
{
    public function __construct(
        private readonly CostService $costService,
        private readonly AccountingEventService $accountingEvents,
    ) {}

    public function postPurchaseReturn(PurchaseReturn $return): void
    {
        $return->loadMissing(['items', 'purchaseOrder', 'supplier', 'grn']);

        $exchangeRate = (float) ($return->purchaseOrder?->exchange_rate ?? 1);
        $amount = round((float) $return->items->sum('line_total'), 2);
        $functionalAmount = round($amount * $exchangeRate, 2);

        if ($functionalAmount <= 0) {
            return;
        }

        $userId = (int) ($return->updated_by ?? $return->created_by ?? 0);

        $this->accountingEvents->process(
            'purchase.returned',
            PurchaseReturn::class,
            (int) $return->id,
            [
                'date' => now()->toDateString(),
                'branch_id' => $return->branch_id,
                'warehouse_id' => $return->grn?->warehouse_id,
                'currency_code' => $return->purchaseOrder?->currency_code
                    ?? $return->supplier?->currency_code
                    ?? 'USD',
                'exchange_rate' => $exchangeRate,
                'inventory_cost' => $functionalAmount,
                'gross_amount' => $functionalAmount,
                'settlement_amount' => $functionalAmount,
                'source_module' => 'procurement',
                'source_number' => $return->reference_no,
                'description' => __('Purchase return :ref', ['ref' => $return->reference_no]),
                'party_type' => Supplier::class,
                'party_id' => $return->supplier_id,
                'tax_direction' => 'purchase',
                'user_id' => $userId > 0 ? $userId : null,
            ],
            $userId,
        );
    }

    public function applyLandedCost(LandedCostEntry $entry): void
    {
        $this->costService->applyLandedCost($entry);
    }
}
