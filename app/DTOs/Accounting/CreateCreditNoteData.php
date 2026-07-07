<?php

declare(strict_types=1);

namespace App\DTOs\Accounting;

use App\Http\Requests\Admin\Accounting\StoreCreditNoteRequest;

final readonly class CreateCreditNoteData
{
    public function __construct(
        public int $customerId,
        public int $branchId,
        public ?int $saleInvoiceId,
        public string $date,
        public float $amount,
        public float $taxAmount,
        public ?int $taxTypeId,
        public ?string $currencyCode,
        public ?float $exchangeRate,
        public string $reason,
    ) {}

    public static function fromRequest(StoreCreditNoteRequest $request): self
    {
        return new self(
            customerId: (int) $request->validated('customer_id'),
            branchId: (int) $request->validated('branch_id'),
            saleInvoiceId: $request->validated('sale_invoice_id') !== null
                ? (int) $request->validated('sale_invoice_id') : null,
            date: $request->validated('date'),
            amount: (float) $request->validated('amount'),
            taxAmount: (float) ($request->validated('tax_amount') ?? 0),
            taxTypeId: $request->validated('tax_type_id') !== null ? (int) $request->validated('tax_type_id') : null,
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
            'customer_id' => $this->customerId,
            'branch_id' => $this->branchId,
            'sale_invoice_id' => $this->saleInvoiceId,
            'date' => $this->date,
            'amount' => $this->amount,
            'tax_amount' => $this->taxAmount,
            'tax_type_id' => $this->taxTypeId,
            'currency_code' => $this->currencyCode,
            'exchange_rate' => $this->exchangeRate,
            'reason' => $this->reason,
        ];
    }
}
