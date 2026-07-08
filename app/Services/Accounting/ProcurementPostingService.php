<?php

declare(strict_types=1);

namespace App\Services\Accounting;

use App\Models\GoodsReceivingNote;
use App\Models\JournalEntry;
use App\Models\LandedCostEntry;
use App\Models\PurchaseReturn;
use App\Models\Supplier;

/**
 * Posts procurement events to the general ledger via the accounting event pipeline.
 */
final class ProcurementPostingService
{
    public function __construct(
        private readonly AccountingEventService $accountingEvents,
        private readonly FinancialSettingsService $financialSettings,
        private readonly CostService $costService,
    ) {}

    /**
     * Post a goods-receiving note: Dr Inventory, Cr Accounts Payable.
     */
    public function postGrnToGL(GoodsReceivingNote $grn): ?JournalEntry
    {
        if ($grn->is_virtual) {
            return null;
        }

        $grn->loadMissing(['items.purchaseOrderItem', 'purchaseOrder', 'supplier']);

        $exchangeRate = (float) ($grn->purchaseOrder?->exchange_rate ?? 1);
        $landedCost = 0.0;

        foreach ($grn->items as $item) {
            $qty = (float) $item->qty_received;
            $unitPrice = (float) ($item->purchaseOrderItem?->unit_price ?? 0);
            $landedCost += round($qty * $unitPrice * $exchangeRate, 2);
        }

        if ($landedCost <= 0) {
            return null;
        }

        $userId = (int) ($grn->updated_by ?? $grn->created_by ?? 0);
        $settings = $this->financialSettings->get();

        $event = $this->accountingEvents->process(
            'purchase.received',
            GoodsReceivingNote::class,
            (int) $grn->id,
            [
                'date' => $grn->received_at?->toDateString() ?? now()->toDateString(),
                'branch_id' => $grn->branch_id,
                'warehouse_id' => $grn->warehouse_id,
                'currency_code' => $grn->purchaseOrder?->currency_code
                    ?? $grn->supplier?->currency_code
                    ?? 'USD',
                'exchange_rate' => $exchangeRate,
                'landed_cost' => $landedCost,
                'source_module' => 'procurement',
                'source_number' => $grn->reference_no,
                'description' => __('Goods received :ref', ['ref' => $grn->reference_no]),
                'party_type' => Supplier::class,
                'party_id' => $grn->supplier_id,
                'tax_direction' => 'purchase',
                'tax_type_id' => $settings->default_purchase_tax_type_id ?? $settings->default_tax_type_id,
                'user_id' => $userId > 0 ? $userId : null,
            ],
            $userId,
        );

        return $event->journalEntry;
    }

    /**
     * Reverse a purchase return: Dr AP, Cr Inventory.
     */
    public function postPurchaseReturnToGL(PurchaseReturn $return): ?JournalEntry
    {
        $return->loadMissing(['items', 'purchaseOrder', 'supplier', 'grn']);

        $exchangeRate = (float) ($return->purchaseOrder?->exchange_rate ?? 1);
        $amount = round((float) $return->items->sum('line_total'), 2);
        $functionalAmount = round($amount * $exchangeRate, 2);

        if ($functionalAmount <= 0) {
            return null;
        }

        $userId = (int) ($return->updated_by ?? $return->created_by ?? 0);

        $event = $this->accountingEvents->process(
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

        return $event->journalEntry;
    }

    /**
     * Apply landed cost allocation to inventory cost layers.
     */
    public function applyLandedCost(LandedCostEntry $entry): void
    {
        $this->costService->applyLandedCost($entry);
    }
}
