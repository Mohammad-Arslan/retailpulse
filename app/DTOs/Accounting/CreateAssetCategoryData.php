<?php

declare(strict_types=1);

namespace App\DTOs\Accounting;

use App\Http\Requests\Admin\Accounting\StoreAssetCategoryRequest;

final readonly class CreateAssetCategoryData
{
    public function __construct(
        public string $name,
        public string $code,
        public int $defaultUsefulLifeMonths,
        public ?int $assetAccountId,
        public ?int $accumulatedDepreciationAccountId,
        public ?int $depreciationExpenseAccountId,
    ) {}

    public static function fromRequest(StoreAssetCategoryRequest $request): self
    {
        return new self(
            name: $request->validated('name'),
            code: $request->validated('code'),
            defaultUsefulLifeMonths: (int) $request->validated('default_useful_life_months'),
            assetAccountId: $request->validated('asset_account_id') !== null
                ? (int) $request->validated('asset_account_id') : null,
            accumulatedDepreciationAccountId: $request->validated('accumulated_depreciation_account_id') !== null
                ? (int) $request->validated('accumulated_depreciation_account_id') : null,
            depreciationExpenseAccountId: $request->validated('depreciation_expense_account_id') !== null
                ? (int) $request->validated('depreciation_expense_account_id') : null,
        );
    }
}
