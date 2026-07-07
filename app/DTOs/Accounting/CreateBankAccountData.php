<?php

declare(strict_types=1);

namespace App\DTOs\Accounting;

use App\Http\Requests\Admin\Accounting\StoreBankAccountRequest;

final readonly class CreateBankAccountData
{
    public function __construct(
        public ?int $branchId,
        public ?int $legalEntityId,
        public int $coaAccountId,
        public string $bankName,
        public string $accountTitle,
        public ?string $accountNumberMasked,
        public string $currencyCode,
        public string $status,
    ) {}

    public static function fromRequest(StoreBankAccountRequest $request): self
    {
        return new self(
            branchId: $request->validated('branch_id') !== null ? (int) $request->validated('branch_id') : null,
            legalEntityId: $request->validated('legal_entity_id') !== null ? (int) $request->validated('legal_entity_id') : null,
            coaAccountId: (int) $request->validated('coa_account_id'),
            bankName: $request->validated('bank_name'),
            accountTitle: $request->validated('account_title'),
            accountNumberMasked: $request->validated('account_number_masked'),
            currencyCode: $request->validated('currency_code'),
            status: $request->validated('status') ?? 'active',
        );
    }
}
