<?php

declare(strict_types=1);

namespace App\Services\Procurement;

use App\DTOs\Inventory\DeductStockData;
use App\Enums\ProcurementDocumentType;
use App\Enums\PurchaseReturnStatus;
use App\Enums\StockMovementReason;
use App\Enums\SupplierLedgerEntryType;
use App\Events\Procurement\DebitNoteIssued;
use App\Events\Procurement\PurchaseReturnApproved;
use App\Models\DebitNote;
use App\Models\GoodsReceivingNote;
use App\Models\PurchaseReturn;
use App\Services\InventoryService;
use App\Services\Procurement\Contracts\ProcurementPostingHook;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

final class PurchaseReturnService
{
    public function __construct(
        private readonly ProcurementDocumentNumberService $documentNumbers,
        private readonly SupplierLedgerService $ledger,
        private readonly InventoryService $inventory,
        private readonly ProcurementPostingHook $postingHook,
    ) {}

    /**
     * @param  list<array<string, mixed>>  $lines
     */
    public function create(
        GoodsReceivingNote $grn,
        string $reason,
        array $lines,
        int $userId,
        ?string $notes = null,
    ): PurchaseReturn {
        $grn->load(['items.purchaseOrderItem', 'purchaseReturns.items']);

        $returnedByGrnItem = [];
        foreach ($grn->purchaseReturns as $return) {
            if ($return->status === PurchaseReturnStatus::Closed) {
                continue;
            }
            foreach ($return->items as $returnItem) {
                $returnedByGrnItem[$returnItem->grn_item_id] = ($returnedByGrnItem[$returnItem->grn_item_id] ?? 0)
                    + (float) $returnItem->qty_returned;
            }
        }

        return DB::transaction(function () use ($grn, $reason, $lines, $userId, $notes, $returnedByGrnItem) {
            $return = PurchaseReturn::query()->create([
                'branch_id' => $grn->branch_id,
                'supplier_id' => $grn->supplier_id,
                'grn_id' => $grn->id,
                'purchase_order_id' => $grn->purchase_order_id,
                'reference_no' => $this->documentNumbers->next($grn->branch_id, ProcurementDocumentType::PurchaseReturn),
                'status' => PurchaseReturnStatus::Draft,
                'reason' => $reason,
                'notes' => $notes,
                'created_by' => $userId,
                'updated_by' => $userId,
            ]);

            foreach ($lines as $line) {
                $grnItem = $grn->items->firstWhere('id', $line['grn_item_id'] ?? null);
                if ($grnItem === null) {
                    throw ValidationException::withMessages([
                        'lines' => __('Invalid GRN line.'),
                    ]);
                }

                $qtyReturned = (float) ($line['qty_returned'] ?? 0);
                $alreadyReturned = (float) ($returnedByGrnItem[$grnItem->id] ?? 0);
                $available = (float) $grnItem->qty_received - $alreadyReturned;

                if ($qtyReturned <= 0) {
                    continue;
                }

                if ($qtyReturned > $available + 0.0001) {
                    throw ValidationException::withMessages([
                        'lines' => __('Return quantity exceeds available quantity for this GRN line.'),
                    ]);
                }

                $unitCost = (float) ($line['unit_cost'] ?? $grnItem->purchaseOrderItem?->unit_price ?? 0);

                $return->items()->create([
                    'grn_item_id' => $grnItem->id,
                    'product_variant_id' => (int) ($line['product_variant_id'] ?? $grnItem->product_variant_id),
                    'qty_returned' => $qtyReturned,
                    'unit_cost' => $unitCost,
                    'line_total' => round($qtyReturned * $unitCost, 2),
                ]);
            }

            if ($return->items()->count() === 0) {
                throw ValidationException::withMessages([
                    'lines' => __('Add at least one return line with quantity.'),
                ]);
            }

            return $return->fresh(['items']) ?? $return;
        });
    }

    public function approve(PurchaseReturn $return, int $userId): PurchaseReturn
    {
        if (! $return->status->canApprove()) {
            throw ValidationException::withMessages(['status' => __('Only draft returns can be approved.')]);
        }

        $return->update([
            'status' => PurchaseReturnStatus::Approved,
            'approved_by' => $userId,
            'approved_at' => now(),
            'updated_by' => $userId,
        ]);

        event(new PurchaseReturnApproved($return));

        return $return;
    }

    public function dispatchGoods(PurchaseReturn $return, int $userId, int $warehouseId): PurchaseReturn
    {
        if (! $return->status->canDispatch()) {
            throw ValidationException::withMessages(['status' => __('Return must be approved before dispatch.')]);
        }

        $return->load(['items.grnItem', 'grn']);

        return DB::transaction(function () use ($return, $userId, $warehouseId) {
            foreach ($return->items as $item) {
                $this->inventory->deduct(new DeductStockData(
                    warehouseId: $warehouseId,
                    variantId: $item->product_variant_id,
                    batchId: $item->grnItem?->batch_id,
                    quantity: (int) ceil((float) $item->qty_returned),
                    reason: StockMovementReason::ReturnSupplier,
                    userId: $userId,
                    referenceType: PurchaseReturn::class,
                    referenceId: $return->id,
                ));
            }

            $return->update([
                'status' => PurchaseReturnStatus::GoodsDispatched,
                'dispatched_at' => now(),
                'updated_by' => $userId,
            ]);

            return $return;
        });
    }

    public function issueDebitNote(PurchaseReturn $return, int $userId): DebitNote
    {
        if (! $return->status->canIssueDebitNote()) {
            throw ValidationException::withMessages(['status' => __('Debit note cannot be issued in current status.')]);
        }

        $return->load(['items', 'purchaseOrder', 'supplier']);

        $amount = round((float) $return->items->sum('line_total'), 2);

        if ($amount <= 0) {
            throw ValidationException::withMessages([
                'amount' => __('Debit note amount must be greater than zero.'),
            ]);
        }

        $currencyCode = $return->purchaseOrder?->currency_code
            ?? $return->supplier?->currency_code
            ?? 'USD';
        $exchangeRate = (float) ($return->purchaseOrder?->exchange_rate ?? 1);
        $functionalAmount = round($amount * $exchangeRate, 2);

        return DB::transaction(function () use ($return, $amount, $userId, $currencyCode, $exchangeRate, $functionalAmount) {
            $debitNote = DebitNote::query()->create([
                'branch_id' => $return->branch_id,
                'supplier_id' => $return->supplier_id,
                'purchase_return_id' => $return->id,
                'reference_no' => $this->documentNumbers->next($return->branch_id, ProcurementDocumentType::DebitNote),
                'amount' => $amount,
                'currency_code' => $currencyCode,
                'exchange_rate' => $exchangeRate,
                'functional_amount' => $functionalAmount,
                'status' => 'issued',
                'issued_at' => now(),
                'created_by' => $userId,
                'updated_by' => $userId,
            ]);

            $this->ledger->recordEntry(
                supplierId: $return->supplier_id,
                branchId: $return->branch_id,
                type: SupplierLedgerEntryType::DebitNote,
                amount: $amount,
                currencyCode: $debitNote->currency_code,
                exchangeRate: (float) $debitNote->exchange_rate,
                functionalAmount: (float) $debitNote->functional_amount,
                referenceType: DebitNote::class,
                referenceId: $debitNote->id,
                referenceNo: $debitNote->reference_no,
                notes: __('Debit note for return :ref', ['ref' => $return->reference_no]),
                userId: $userId,
            );

            $return->update([
                'status' => PurchaseReturnStatus::DebitNoteIssued,
                'updated_by' => $userId,
            ]);

            event(new DebitNoteIssued($debitNote));
            $this->postingHook->postPurchaseReturn($return->fresh() ?? $return);

            return $debitNote;
        });
    }

    public function acknowledge(PurchaseReturn $return, int $userId): PurchaseReturn
    {
        if (! $return->status->canAcknowledge()) {
            throw ValidationException::withMessages(['status' => __('Return must be dispatched before acknowledgement.')]);
        }

        $return->update([
            'status' => PurchaseReturnStatus::SupplierAcknowledged,
            'updated_by' => $userId,
        ]);

        return $return;
    }

    public function close(PurchaseReturn $return, int $userId): PurchaseReturn
    {
        if (! $return->status->canClose()) {
            throw ValidationException::withMessages(['status' => __('Return must have a debit note before closing.')]);
        }

        $return->update([
            'status' => PurchaseReturnStatus::Closed,
            'updated_by' => $userId,
        ]);

        return $return;
    }
}
