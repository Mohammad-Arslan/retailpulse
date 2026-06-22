<?php

declare(strict_types=1);

namespace App\Services\Procurement;

use App\Enums\PoMatchStatus;
use App\Enums\SupplierInvoiceStatus;
use App\Models\PoMatchResult;
use App\Models\SupplierInvoice;
use Illuminate\Validation\ValidationException;

final class ThreeWayMatchingService
{
    public function __construct(
        private readonly ProcurementConfigService $config,
    ) {}

    public function match(SupplierInvoice $invoice, int $userId): PoMatchResult
    {
        $invoice->load(['items', 'purchaseOrder.items', 'grn.items']);

        if ($invoice->purchase_order_id === null) {
            throw ValidationException::withMessages([
                'purchase_order_id' => __('Purchase order is required for three-way matching.'),
            ]);
        }

        $config = $this->config->resolve($invoice->branch_id);
        $priceTolerance = (float) $config['matching_price_tolerance_percent'];
        $qtyTolerance = (float) $config['matching_quantity_tolerance_percent'];

        $qtyVariance = 0.0;
        $priceVariance = 0.0;
        $exceptions = [];

        foreach ($invoice->items as $invItem) {
            $poItem = $invoice->purchaseOrder?->items
                ->firstWhere('product_variant_id', $invItem->product_variant_id);

            $grnItem = $invoice->grn?->items
                ->firstWhere('product_variant_id', $invItem->product_variant_id);

            if ($poItem === null) {
                $exceptions[] = __('Extra line on invoice: variant #:id', ['id' => $invItem->product_variant_id]);

                continue;
            }

            if ($grnItem === null) {
                $exceptions[] = __('Missing GRN line for variant #:id', ['id' => $invItem->product_variant_id]);

                continue;
            }

            $qtyDiff = abs((float) $invItem->qty_invoiced - (float) $grnItem->qty_received);
            $qtyVariance += $qtyDiff;

            $poPrice = (float) $poItem->unit_price;
            $invPrice = (float) $invItem->unit_price;
            $priceDiffPct = $poPrice > 0 ? abs($invPrice - $poPrice) / $poPrice * 100 : 0;
            $priceVariance += $priceDiffPct;

            if ($qtyTolerance === 0.0 && $qtyDiff > 0.0001) {
                $exceptions[] = __('Quantity mismatch on variant #:id', ['id' => $invItem->product_variant_id]);
            } elseif ($qtyTolerance > 0 && $poItem->qty_ordered > 0) {
                $qtyDiffPct = $qtyDiff / (float) $poItem->qty_ordered * 100;
                if ($qtyDiffPct > $qtyTolerance) {
                    $exceptions[] = __('Quantity variance exceeds tolerance on variant #:id', ['id' => $invItem->product_variant_id]);
                }
            }

            if ($priceDiffPct > $priceTolerance) {
                $exceptions[] = __('Price variance exceeds tolerance on variant #:id', ['id' => $invItem->product_variant_id]);
            }
        }

        $poVariantIds = $invoice->purchaseOrder?->items->pluck('product_variant_id')->all() ?? [];
        $invVariantIds = $invoice->items->pluck('product_variant_id')->all();

        foreach ($poVariantIds as $variantId) {
            if (! in_array($variantId, $invVariantIds, true)) {
                $exceptions[] = __('Missing invoice line for variant #:id', ['id' => $variantId]);
            }
        }

        $status = match (true) {
            $exceptions === [] => PoMatchStatus::FullyMatched,
            count($exceptions) <= 2 => PoMatchStatus::PartiallyMatched,
            default => PoMatchStatus::Unmatched,
        };

        return PoMatchResult::query()->updateOrCreate(
            ['supplier_invoice_id' => $invoice->id],
            [
                'purchase_order_id' => $invoice->purchase_order_id,
                'grn_id' => $invoice->grn_id,
                'match_status' => $status,
                'qty_variance' => $qtyVariance,
                'price_variance' => $priceVariance,
                'exception_reason' => $exceptions !== [] ? implode('; ', $exceptions) : null,
                'matched_by' => $userId,
                'matched_at' => now(),
            ],
        );
    }

    public function resolveException(PoMatchResult $result, int $userId): PoMatchResult
    {
        $result->update([
            'match_status' => PoMatchStatus::FullyMatched,
            'resolved_by' => $userId,
            'resolved_at' => now(),
            'exception_reason' => null,
        ]);

        $result->supplierInvoice?->update(['status' => SupplierInvoiceStatus::Matched]);

        return $result->fresh() ?? $result;
    }
}
