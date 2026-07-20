<?php

declare(strict_types=1);

namespace App\Services\Accounting;

use App\DTOs\Accounting\CreateDebitNoteData;
use App\Enums\ProcurementDocumentType;
use App\Enums\SupplierInvoiceStatus;
use App\Enums\SupplierLedgerEntryType;
use App\Events\Procurement\DebitNoteIssued;
use App\Models\DebitNote;
use App\Models\Supplier;
use App\Models\SupplierInvoice;
use App\Services\Procurement\ProcurementDocumentNumberService;
use App\Services\Procurement\SupplierLedgerService;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

final class DebitNoteService
{
    public function __construct(
        private readonly ProcurementDocumentNumberService $documentNumbers,
        private readonly SupplierLedgerService $ledger,
    ) {}

    public function create(CreateDebitNoteData $data, int $userId): DebitNote
    {
        $amount = $data->amount;

        if ($amount <= 0) {
            throw ValidationException::withMessages([
                'amount' => __('Debit note amount must be greater than zero.'),
            ]);
        }

        $supplier = Supplier::query()->find($data->supplierId);

        if ($supplier === null) {
            throw ValidationException::withMessages([
                'supplier_id' => __('Supplier not found.'),
            ]);
        }

        $invoice = null;

        if ($data->supplierInvoiceId !== null) {
            $invoice = SupplierInvoice::query()->find($data->supplierInvoiceId);

            if ($invoice === null || $invoice->supplier_id !== $supplier->id) {
                throw ValidationException::withMessages([
                    'supplier_invoice_id' => __('Supplier invoice does not belong to this supplier.'),
                ]);
            }

            if (! in_array($invoice->status, [SupplierInvoiceStatus::Matched, SupplierInvoiceStatus::Approved, SupplierInvoiceStatus::Paid], true)) {
                throw ValidationException::withMessages([
                    'supplier_invoice_id' => __('Debit note cannot be issued against this invoice in its current status.'),
                ]);
            }
        }

        $currencyCode = $data->currencyCode ?? $invoice?->currency_code ?? $supplier->currency_code ?? 'USD';
        $exchangeRate = $data->exchangeRate ?? (float) ($invoice?->exchange_rate ?? 1);
        $functionalAmount = round($amount * $exchangeRate, 2);

        if ($invoice !== null) {
            $alreadyDebited = (float) DebitNote::query()
                ->where('supplier_invoice_id', $invoice->id)
                ->sum('functional_amount');
            $remaining = (float) $invoice->functional_total - $alreadyDebited;

            if ($functionalAmount > $remaining + 0.0001) {
                throw ValidationException::withMessages([
                    'amount' => __('Debit note amount exceeds the remaining balance of the supplier invoice.'),
                ]);
            }
        }

        return DB::transaction(function () use ($data, $userId, $amount, $currencyCode, $exchangeRate, $functionalAmount) {
            $debitNote = DebitNote::query()->create([
                'branch_id' => $data->branchId,
                'supplier_id' => $data->supplierId,
                'purchase_return_id' => $data->purchaseReturnId,
                'supplier_invoice_id' => $data->supplierInvoiceId,
                'reference_no' => $this->documentNumbers->next($data->branchId, ProcurementDocumentType::DebitNote),
                'amount' => $amount,
                'currency_code' => $currencyCode,
                'exchange_rate' => $exchangeRate,
                'functional_amount' => $functionalAmount,
                'status' => 'issued',
                'issued_at' => $data->date,
                'notes' => $data->reason,
                'created_by' => $userId,
                'updated_by' => $userId,
            ]);

            $this->ledger->recordEntry(
                supplierId: $data->supplierId,
                branchId: $data->branchId,
                type: SupplierLedgerEntryType::DebitNote,
                amount: $amount,
                currencyCode: $currencyCode,
                exchangeRate: $exchangeRate,
                functionalAmount: $functionalAmount,
                referenceType: DebitNote::class,
                referenceId: $debitNote->id,
                referenceNo: $debitNote->reference_no,
                notes: $data->reason,
                userId: $userId,
            );

            event(new DebitNoteIssued($debitNote));

            return $debitNote->fresh(['supplier', 'branch']) ?? $debitNote;
        });
    }
}
