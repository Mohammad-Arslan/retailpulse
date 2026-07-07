<?php

declare(strict_types=1);

namespace App\DTOs\Accounting;

use App\Http\Requests\Admin\Accounting\StoreFixedAssetRequest;

final readonly class CreateFixedAssetData
{
    public function __construct(
        public string $assetCode,
        public string $name,
        public int $categoryId,
        public float $acquisitionCost,
        public string $acquisitionDate,
        public int $usefulLifeMonths,
        public float $salvageValue,
        public ?int $branchId,
        public ?int $legalEntityId,
        public ?string $location,
    ) {}

    public static function fromRequest(StoreFixedAssetRequest $request): self
    {
        return new self(
            assetCode: $request->validated('asset_code'),
            name: $request->validated('name'),
            categoryId: (int) $request->validated('category_id'),
            acquisitionCost: (float) $request->validated('acquisition_cost'),
            acquisitionDate: $request->validated('acquisition_date'),
            usefulLifeMonths: (int) $request->validated('useful_life_months'),
            salvageValue: (float) ($request->validated('salvage_value') ?? 0),
            branchId: $request->validated('branch_id') !== null ? (int) $request->validated('branch_id') : null,
            legalEntityId: $request->validated('legal_entity_id') !== null ? (int) $request->validated('legal_entity_id') : null,
            location: $request->validated('location'),
        );
    }
}
