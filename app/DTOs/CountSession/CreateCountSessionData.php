<?php

declare(strict_types=1);

namespace App\DTOs\CountSession;

use App\Enums\CountScopeType;
use App\Http\Requests\Admin\StoreCountSessionRequest;

final readonly class CreateCountSessionData
{
    public function __construct(
        public int $branchId,
        public int $warehouseId,
        public CountScopeType $scopeType,
        public ?int $scopeId,
        public bool $blindCount,
        public bool $freezeMode,
        public ?float $varianceThresholdPct,
        public ?float $varianceThresholdValue,
        public int $userId,
    ) {}

    public static function fromRequest(StoreCountSessionRequest $request): self
    {
        $validated = $request->validated();

        return new self(
            branchId: (int) $validated['branch_id'],
            warehouseId: (int) $validated['warehouse_id'],
            scopeType: CountScopeType::from($validated['scope_type']),
            scopeId: isset($validated['scope_id']) ? (int) $validated['scope_id'] : null,
            blindCount: (bool) ($validated['blind_count'] ?? false),
            freezeMode: (bool) ($validated['freeze_mode'] ?? false),
            varianceThresholdPct: isset($validated['variance_threshold_pct'])
                ? (float) $validated['variance_threshold_pct']
                : null,
            varianceThresholdValue: isset($validated['variance_threshold_value'])
                ? (float) $validated['variance_threshold_value']
                : null,
            userId: (int) $request->user()->id,
        );
    }
}
