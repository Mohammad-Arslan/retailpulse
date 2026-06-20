<?php

declare(strict_types=1);

namespace App\DTOs\CountSchedule;

use App\Enums\CountScheduleFrequency;
use App\Enums\CountScopeType;
use App\Http\Requests\Admin\UpdateCountScheduleRuleRequest;

final readonly class UpdateCountScheduleRuleData
{
    public function __construct(
        public CountScopeType $scopeType,
        public ?int $scopeId,
        public CountScheduleFrequency $frequency,
        public ?int $dayOfWeek,
        public ?int $dayOfMonth,
        public bool $blindCount,
        public bool $isActive,
    ) {}

    public static function fromRequest(UpdateCountScheduleRuleRequest $request): self
    {
        $validated = $request->validated();

        return new self(
            scopeType: CountScopeType::from($validated['scope_type']),
            scopeId: isset($validated['scope_id']) ? (int) $validated['scope_id'] : null,
            frequency: CountScheduleFrequency::from($validated['frequency']),
            dayOfWeek: isset($validated['day_of_week']) ? (int) $validated['day_of_week'] : null,
            dayOfMonth: isset($validated['day_of_month']) ? (int) $validated['day_of_month'] : null,
            blindCount: (bool) ($validated['blind_count'] ?? false),
            isActive: (bool) ($validated['is_active'] ?? true),
        );
    }
}
