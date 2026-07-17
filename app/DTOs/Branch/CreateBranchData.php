<?php

declare(strict_types=1);

namespace App\DTOs\Branch;

use App\Http\Requests\Admin\StoreBranchRequest;
use App\Support\BranchOperationalOptions;
use App\Support\OperatingHours;

final readonly class CreateBranchData
{
    /**
     * @param  array<string, array{open: string, close: string, closed: bool}>  $operatingHours
     * @param  ?list<int>  $weekendDays
     */
    public function __construct(
        public string $name,
        public ?string $address,
        public string $currency,
        public string $timezone,
        public array $operatingHours,
        public ?array $weekendDays,
        public ?string $receiptFooter,
        public bool $isActive,
        public ?int $initialWarehouseId,
    ) {}

    public static function fromRequest(StoreBranchRequest $request): self
    {
        $initialWarehouseId = $request->validated('initial_warehouse_id');
        $weekendDays = $request->validated('weekend_days');

        return new self(
            name: $request->validated('name'),
            address: $request->validated('address'),
            currency: BranchOperationalOptions::normalizeCurrency($request->validated('currency')),
            timezone: BranchOperationalOptions::normalizeTimezone($request->validated('timezone')),
            operatingHours: OperatingHours::normalize($request->validated('operating_hours')),
            weekendDays: is_array($weekendDays) ? array_values(array_map('intval', $weekendDays)) : null,
            receiptFooter: $request->validated('receipt_footer'),
            isActive: $request->boolean('is_active', true),
            initialWarehouseId: $initialWarehouseId !== null ? (int) $initialWarehouseId : null,
        );
    }
}
