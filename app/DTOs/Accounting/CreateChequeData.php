<?php

declare(strict_types=1);

namespace App\DTOs\Accounting;

use App\Http\Requests\Admin\Accounting\StoreChequeRequest;

final readonly class CreateChequeData
{
    public function __construct(
        public string $type,
        public string $partyType,
        public int $partyId,
        public float $amount,
        public string $chequeNo,
        public ?string $bank,
        public ?string $dueDate,
        public ?int $branchId,
        public ?string $currencyCode,
    ) {}

    public static function fromRequest(StoreChequeRequest $request): self
    {
        return new self(
            type: $request->validated('type'),
            partyType: $request->validated('party_type'),
            partyId: (int) $request->validated('party_id'),
            amount: (float) $request->validated('amount'),
            chequeNo: $request->validated('cheque_no'),
            bank: $request->validated('bank'),
            dueDate: $request->validated('due_date'),
            branchId: $request->validated('branch_id') !== null ? (int) $request->validated('branch_id') : null,
            currencyCode: $request->validated('currency_code'),
        );
    }

    /**
     * @return array<string, mixed>
     */
    public function toArray(): array
    {
        return [
            'type' => $this->type,
            'party_type' => $this->partyType,
            'party_id' => $this->partyId,
            'amount' => $this->amount,
            'cheque_no' => $this->chequeNo,
            'bank' => $this->bank,
            'due_date' => $this->dueDate,
            'branch_id' => $this->branchId,
            'currency_code' => $this->currencyCode,
        ];
    }
}
