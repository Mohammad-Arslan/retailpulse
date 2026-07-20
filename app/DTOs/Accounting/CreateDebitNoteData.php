<?php

declare(strict_types=1);

namespace App\DTOs\Accounting;

use App\Http\Requests\Admin\Accounting\StoreDebitNoteRequest;

final readonly class CreateDebitNoteData
{
    public function __construct(
        public int $supplierId,
        public int $branchId,
        public ?int $purchaseReturnId,
        public ?int $supplierInvoiceId,
        public string $date,
        public float $amount,
        public ?string $currencyCode,
        public ?float $exchangeRate,
        public string $reason,
    ) {}

    public static function fromRequest(StoreDebitNoteRequest $request): self
    {
        return new self(
            supplierId: (int) $request->validated('supplier_id'),
            branchId: (int) $request->validated('branch_id'),
            purchaseReturnId: $request->validated('purchase_return_id') !== null
                ? (int) $request->validated('purchase_return_id') : null,
            supplierInvoiceId: $request->validated('supplier_invoice_id') !== null
                ? (int) $request->validated('supplier_invoice_id') : null,
            date: $request->validated('date'),
            amount: (float) $request->validated('amount'),
            currencyCode: $request->validated('currency_code'),
            exchangeRate: $request->validated('exchange_rate') !== null ? (float) $request->validated('exchange_rate') : null,
            reason: $request->validated('reason'),
        );
    }

    /**
     * @return array<string, mixed>
     */
    public function toArray(): array
    {
        return [
            'supplier_id' => $this->supplierId,
            'branch_id' => $this->branchId,
            'purchase_return_id' => $this->purchaseReturnId,
            'supplier_invoice_id' => $this->supplierInvoiceId,
            'date' => $this->date,
            'amount' => $this->amount,
            'currency_code' => $this->currencyCode,
            'exchange_rate' => $this->exchangeRate,
            'reason' => $this->reason,
        ];
    }
}
