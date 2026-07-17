<?php

declare(strict_types=1);

namespace App\DTOs\Branch;

use App\Enums\PickingStrategy;
use App\Http\Requests\Admin\UpdateBranchRequest;
use App\Support\BranchOperationalOptions;
use App\Support\OperatingHours;

final readonly class UpdateBranchData
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
        public PickingStrategy $pickingStrategy,
        public array $operatingHours,
        public ?array $weekendDays,
        public ?string $receiptFooter,
        public bool $isActive,
        public ?int $defaultWarehouseId,
        public ?string $cutoverDate,
    ) {}

    public static function fromRequest(UpdateBranchRequest $request): self
    {
        $weekendDays = $request->validated('weekend_days');

        return new self(
            name: $request->validated('name'),
            address: $request->validated('address'),
            currency: BranchOperationalOptions::normalizeCurrency($request->validated('currency')),
            timezone: BranchOperationalOptions::normalizeTimezone($request->validated('timezone')),
            pickingStrategy: PickingStrategy::from($request->validated('picking_strategy')),
            operatingHours: OperatingHours::normalize($request->validated('operating_hours')),
            weekendDays: is_array($weekendDays) ? array_values(array_map('intval', $weekendDays)) : null,
            receiptFooter: $request->validated('receipt_footer'),
            isActive: $request->boolean('is_active', true),
            defaultWarehouseId: $request->validated('default_warehouse_id'),
            cutoverDate: $request->validated('cutover_date'),
        );
    }
}
