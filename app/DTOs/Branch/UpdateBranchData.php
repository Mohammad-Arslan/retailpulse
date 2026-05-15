<?php

declare(strict_types=1);

namespace App\DTOs\Branch;

use App\Http\Requests\Admin\UpdateBranchRequest;
use App\Support\OperatingHours;

final readonly class UpdateBranchData
{
    /**
     * @param  array<string, array{open: string, close: string, closed: bool}>  $operatingHours
     */
    public function __construct(
        public string $name,
        public string $code,
        public ?string $address,
        public string $currency,
        public string $timezone,
        public array $operatingHours,
        public ?string $receiptFooter,
        public bool $isActive,
        public ?int $defaultWarehouseId,
    ) {}

    public static function fromRequest(UpdateBranchRequest $request): self
    {
        return new self(
            name: $request->validated('name'),
            code: $request->validated('code'),
            address: $request->validated('address'),
            currency: strtoupper($request->validated('currency')),
            timezone: $request->validated('timezone'),
            operatingHours: OperatingHours::normalize($request->validated('operating_hours')),
            receiptFooter: $request->validated('receipt_footer'),
            isActive: $request->boolean('is_active', true),
            defaultWarehouseId: $request->validated('default_warehouse_id'),
        );
    }
}
