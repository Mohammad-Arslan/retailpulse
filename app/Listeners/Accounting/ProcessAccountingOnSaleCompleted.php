<?php

declare(strict_types=1);

namespace App\Listeners\Accounting;

use App\Events\SaleCompleted;
use App\Models\Customer;
use App\Models\Sale;
use App\Services\Accounting\AccountingEventService;
use App\Services\Accounting\CostService;
use Illuminate\Support\Facades\DB;

final class ProcessAccountingOnSaleCompleted
{
    public function __construct(
        private readonly CostService $costService,
        private readonly AccountingEventService $accountingEvents,
    ) {}

    public function handle(SaleCompleted $event): void
    {
        $sale = $event->sale;

        if ($sale->is_historical) {
            return;
        }

        $sale->loadMissing(['items', 'payments', 'invoice']);

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

            $payload = [
                'date' => $sale->completed_at?->toDateString() ?? now()->toDateString(),
                'branch_id' => $sale->branch_id,
                'warehouse_id' => $sale->warehouse_id,
                'currency_code' => $sale->currency ?? 'USD',
                'gross_amount' => (float) $sale->grand_total,
                'net_amount' => round((float) $sale->subtotal - (float) $sale->total_discount, 2),
                'tax_amount' => (float) $sale->tax_total,
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

            $this->accountingEvents->process(
                'sale.completed',
                Sale::class,
                (int) $sale->id,
                $payload,
                (int) ($sale->cashier_id ?? 0),
            );
        });
    }
}
