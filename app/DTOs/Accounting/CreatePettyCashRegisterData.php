<?php

declare(strict_types=1);

namespace App\DTOs\Accounting;

use App\Enums\PettyCashRegisterMode;
use App\Http\Requests\Admin\Accounting\StorePettyCashRegisterRequest;

final readonly class CreatePettyCashRegisterData
{
    public function __construct(
        public int $branchId,
        public string $name,
        public int $coaAccountId,
        public float $openingBalance,
        public PettyCashRegisterMode $registerMode,
    ) {}

    public static function fromRequest(StorePettyCashRegisterRequest $request): self
    {
        return new self(
            branchId: (int) $request->validated('branch_id'),
            name: $request->validated('name'),
            coaAccountId: (int) $request->validated('coa_account_id'),
            openingBalance: (float) ($request->validated('opening_balance') ?? 0),
            registerMode: PettyCashRegisterMode::from($request->validated('register_mode')),
        );
    }
}
