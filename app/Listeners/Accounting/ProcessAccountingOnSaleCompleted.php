<?php

declare(strict_types=1);

namespace App\Listeners\Accounting;

use App\Enums\AccountingEventStatus;
use App\Events\SaleCompleted;
use App\Models\AccountingEvent;
use App\Models\Customer;
use App\Models\Sale;
use App\Services\Accounting\AccountingEventService;
use App\Services\Accounting\CostService;
use App\Services\Accounting\FinancialSettingsService;
use Illuminate\Support\Facades\DB;

final class ProcessAccountingOnSaleCompleted
{
    public function __construct(
        private readonly CostService $costService,
        private readonly AccountingEventService $accountingEvents,
        private readonly FinancialSettingsService $financialSettings,
    ) {}

    public function handle(SaleCompleted $event): void
    {
        $sale = $event->sale;

        if ($sale->is_historical) {
            return;
        }

        $sale->loadMissing(['items', 'payments', 'invoice']);

        $idempotencyKey = 'sale.completed:'.Sale::class.':'.$sale->id;
        $existingEvent = AccountingEvent::query()
            ->where('idempotency_key', $idempotencyKey)
            ->first();

        if (in_array($existingEvent?->processing_status, [
            AccountingEventStatus::Completed,
            AccountingEventStatus::Skipped,
        ], true)) {
            return;
        }

        if ($sale->items->contains(fn ($item) => $item->cogs_journal_entry_id !== null)) {
            return;
        }

        DB::transaction(function () use ($sale) {
            $inventoryCost = 0.0;
            $itemCosts = [];

            foreach ($sale->items as $item) {
                $lineCost = $this->costService->consumeOnSale($item);
                $inventoryCost += $lineCost;
                $itemCosts[] = [
                    'sale_item_id' => $item->id,
                    'product_variant_id' => $item->product_variant_id,
                    'quantity' => $item->quantity,
                    'line_total' => (float) $item->line_total,
                    'tax_amount' => (float) $item->tax_amount,
                    'inventory_cost' => $lineCost,
                ];
            }

            $primaryPayment = $sale->payments->first();
            $settings = $this->financialSettings->get();

            $payload = [
                'date' => $sale->completed_at?->toDateString() ?? now()->toDateString(),
                'branch_id' => $sale->branch_id,
                'warehouse_id' => $sale->warehouse_id,
                'currency_code' => $sale->currency ?? 'USD',
                'gross_amount' => (float) $sale->grand_total,
                'net_amount' => round((float) $sale->subtotal - (float) $sale->total_discount, 2),
                'tax_amount' => (float) $sale->tax_total,
                'tax_type_id' => $settings->default_sales_tax_type_id ?? $settings->default_tax_type_id,
                'tax_direction' => 'sales',
                'discount_amount' => (float) $sale->total_discount,
                'inventory_cost' => round($inventoryCost, 2),
                'settlement_amount' => (float) $sale->grand_total,
                'payment_method' => $primaryPayment?->method->value ?? 'cash',
                'payments' => $sale->payments->map(static fn ($payment) => [
                    'method' => $payment->method->value,
                    'amount' => (float) $payment->amount,
                    'status' => $payment->status->value,
                ])->values()->all(),
                'items' => $itemCosts,
                'source_module' => 'sales',
                'source_number' => $sale->invoice?->invoice_number,
                'description' => __('Sale #:id', ['id' => $sale->id]),
                'party_type' => $sale->customer_id ? Customer::class : null,
                'party_id' => $sale->customer_id,
                'user_id' => $sale->cashier_id,
            ];

            $accountingEvent = $this->accountingEvents->process(
                'sale.completed',
                Sale::class,
                (int) $sale->id,
                $payload,
                (int) ($sale->cashier_id ?? 0),
            );

            $journalEntryId = $accountingEvent->journal_entry_id;

            foreach ($sale->items as $index => $item) {
                $lineCost = $itemCosts[$index]['inventory_cost'] ?? 0.0;

                $item->update([
                    'cost_consumed' => $lineCost > 0 ? $lineCost : null,
                    'cogs_journal_entry_id' => $journalEntryId,
                ]);
            }
        });
    }
}
