<?php

declare(strict_types=1);

namespace App\Listeners\Accounting;

use App\Events\Procurement\GoodsReceived;
use App\Models\FinancialSetting;
use App\Models\GoodsReceivingNote;
use App\Models\Supplier;
use App\Services\Accounting\AccountingEventService;
use App\Services\Accounting\FinancialSettingsService;

final class ProcessAccountingOnGoodsReceived
{
    public function __construct(
        private readonly AccountingEventService $accountingEvents,
        private readonly FinancialSettingsService $financialSettings,
    ) {}

    public function handle(GoodsReceived $event): void
    {
        $grn = $event->grn;

        if ($grn->is_virtual) {
            return;
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
            return;
        }

        $userId = (int) ($grn->updated_by ?? $grn->created_by ?? 0);
        $settings = $this->financialSettings->get();

        $this->accountingEvents->process(
            'purchase.received',
            GoodsReceivingNote::class,
            (int) $grn->id,
            $this->buildGrnPayload($grn, $exchangeRate, $landedCost, $userId, $settings),
            $userId,
        );
    }

    /**
     * @return array<string, mixed>
     */
    private function buildGrnPayload(
        GoodsReceivingNote $grn,
        float $exchangeRate,
        float $landedCost,
        int $userId,
        FinancialSetting $settings,
    ): array {
        return [
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
        ];
    }
}
