<?php

declare(strict_types=1);

namespace App\Services\Procurement;

use App\Enums\PoMatchStatus;
use App\Enums\ProcurementDocumentType;
use App\Enums\SupplierInvoiceStatus;
use App\Events\Procurement\SupplierInvoiceCreated;
use App\Events\Procurement\SupplierInvoiceMatched;
use App\Models\GoodsReceivingNote;
use App\Models\GrnItem;
use App\Models\SupplierInvoice;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

final class SupplierInvoiceService
{
    public function __construct(
        private readonly ProcurementDocumentNumberService $documentNumbers,
        private readonly ThreeWayMatchingService $matching,
        private readonly SupplierLedgerService $ledger,
    ) {}

    /**
     * @param  list<array<string, mixed>>  $lines
     */
    public function createFromGrn(
        GoodsReceivingNote $grn,
        string $invoiceDate,
        ?string $dueDate,
        array $lines,
        int $userId,
        ?string $notes = null,
    ): SupplierInvoice {
        $grn->load(['purchaseOrder', 'items.purchaseOrderItem', 'supplier']);

        if (SupplierInvoice::query()->where('grn_id', $grn->id)->exists()) {
            throw ValidationException::withMessages([
                'grn_id' => __('A supplier invoice already exists for this GRN.'),
            ]);
        }

        return DB::transaction(function () use ($grn, $invoiceDate, $dueDate, $lines, $userId, $notes) {
            $totals = $this->calculateLineTotals($lines, (float) $grn->purchaseOrder->exchange_rate);
            $resolvedDueDate = $this->resolveDueDate($grn, $invoiceDate, $dueDate);

            $invoice = SupplierInvoice::query()->create([
                'branch_id' => $grn->branch_id,
                'supplier_id' => $grn->supplier_id,
                'grn_id' => $grn->id,
                'purchase_order_id' => $grn->purchase_order_id,
                'reference_no' => $this->documentNumbers->next($grn->branch_id, ProcurementDocumentType::SupplierInvoice),
                'status' => SupplierInvoiceStatus::Draft,
                'invoice_date' => $invoiceDate,
                'due_date' => $resolvedDueDate,
                'currency_code' => $grn->purchaseOrder->currency_code,
                'exchange_rate' => $grn->purchaseOrder->exchange_rate,
                'subtotal' => $totals['subtotal'],
                'tax_total' => $totals['tax_total'],
                'discount_total' => $totals['discount_total'],
                'total' => $totals['total'],
                'functional_total' => $totals['functional_total'],
                'notes' => $notes,
                'created_by' => $userId,
                'updated_by' => $userId,
            ]);

            foreach ($lines as $line) {
                $invoice->items()->create($line);
            }

            event(new SupplierInvoiceCreated($invoice));

            $matchResult = $this->matching->match($invoice->fresh(['items', 'purchaseOrder.items', 'grn.items']) ?? $invoice, $userId);

            event(new SupplierInvoiceMatched($invoice, $matchResult));

            if ($matchResult->match_status === PoMatchStatus::FullyMatched) {
                $invoice->update(['status' => SupplierInvoiceStatus::Matched]);
                $this->ledger->recordInvoiceIfMissing($invoice->fresh() ?? $invoice, $userId);
            }

            return $invoice->fresh(['items', 'matchResult']) ?? $invoice;
        });
    }

    public function approve(SupplierInvoice $invoice, int $userId): SupplierInvoice
    {
        if (! $invoice->status->canApprove()) {
            throw ValidationException::withMessages(['status' => __('Only matched invoices can be approved.')]);
        }

        $invoice->update([
            'status' => SupplierInvoiceStatus::Approved,
            'updated_by' => $userId,
        ]);

        return $invoice;
    }

    public function createFromGrnIfMissing(GoodsReceivingNote $grn, int $userId): ?SupplierInvoice
    {
        if (SupplierInvoice::query()->where('grn_id', $grn->id)->exists()) {
            return null;
        }

        $grn->load(['items.purchaseOrderItem', 'purchaseOrder', 'supplier']);

        $invoiceDate = now()->toDateString();
        $dueDate = $this->resolveDueDate($grn, $invoiceDate, null);

        $lines = $grn->items->map(function (GrnItem $item) use ($grn) {
            $qty = (float) $item->qty_received;
            $price = (float) ($item->purchaseOrderItem?->unit_price ?? 0);
            $lineTotal = round($qty * $price, 2);
            $exchangeRate = (float) ($grn->purchaseOrder?->exchange_rate ?? 1);

            return [
                'grn_item_id' => $item->id,
                'purchase_order_item_id' => $item->purchase_order_item_id,
                'product_variant_id' => $item->product_variant_id,
                'qty_invoiced' => $qty,
                'unit_price' => $price,
                'tax_rate' => 0,
                'discount_amount' => 0,
                'line_total' => $lineTotal,
                'functional_line_total' => round($lineTotal * $exchangeRate, 2),
            ];
        })->all();

        if ($lines === []) {
            return null;
        }

        return $this->createFromGrn(
            $grn,
            $invoiceDate,
            $dueDate,
            $lines,
            $userId,
        );
    }

    private function resolveDueDate(GoodsReceivingNote $grn, string $invoiceDate, ?string $dueDate): ?string
    {
        if ($dueDate !== null) {
            return $dueDate;
        }

        $terms = $grn->supplier?->payment_terms_days;
        if ($terms === null || (int) $terms <= 0) {
            return null;
        }

        return Carbon::parse($invoiceDate)->addDays((int) $terms)->toDateString();
    }

    /**
     * @param  list<array<string, mixed>>  $lines
     * @return array{subtotal: float, tax_total: float, discount_total: float, total: float, functional_total: float}
     */
    private function calculateLineTotals(array $lines, float $exchangeRate): array
    {
        $subtotal = 0.0;
        $taxTotal = 0.0;
        $discountTotal = 0.0;

        foreach ($lines as $line) {
            $qty = (float) ($line['qty_invoiced'] ?? 0);
            $price = (float) ($line['unit_price'] ?? 0);
            $taxRate = (float) ($line['tax_rate'] ?? 0);
            $discount = (float) ($line['discount_amount'] ?? 0);
            $lineSubtotal = ($qty * $price) - $discount;
            $subtotal += $lineSubtotal;
            $taxTotal += $lineSubtotal * ($taxRate / 100);
            $discountTotal += $discount;
        }

        $total = $subtotal + $taxTotal;

        return [
            'subtotal' => round($subtotal, 2),
            'tax_total' => round($taxTotal, 2),
            'discount_total' => round($discountTotal, 2),
            'total' => round($total, 2),
            'functional_total' => round($total * $exchangeRate, 2),
        ];
    }
}
