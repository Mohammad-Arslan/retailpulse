<?php

declare(strict_types=1);

namespace App\Services;

use App\DTOs\CountSchedule\CreateCountScheduleRuleData;
use App\DTOs\CountSchedule\UpdateCountScheduleRuleData;
use App\Enums\CountScopeType;
use App\Models\CountScheduleRule;
use Illuminate\Validation\ValidationException;

final class CountScheduleRuleService
{
    public function create(CreateCountScheduleRuleData $data): CountScheduleRule
    {
        $this->assertScope($data->scopeType, $data->scopeId);

        return CountScheduleRule::query()->create([
            'branch_id' => $data->branchId,
            'warehouse_id' => $data->warehouseId,
            'scope_type' => $data->scopeType,
            'scope_id' => $data->scopeId,
            'frequency' => $data->frequency,
            'day_of_week' => $data->dayOfWeek,
            'day_of_month' => $data->dayOfMonth,
            'blind_count' => $data->blindCount,
            'freeze_mode' => $data->freezeMode,
            'is_active' => true,
        ]);
    }

    public function update(CountScheduleRule $rule, UpdateCountScheduleRuleData $data): CountScheduleRule
    {
        $this->assertScope($data->scopeType, $data->scopeId);

        $rule->update([
            'scope_type' => $data->scopeType,
            'scope_id' => $data->scopeId,
            'frequency' => $data->frequency,
            'day_of_week' => $data->dayOfWeek,
            'day_of_month' => $data->dayOfMonth,
            'blind_count' => $data->blindCount,
            'freeze_mode' => $data->freezeMode,
            'is_active' => $data->isActive,
        ]);

        return $rule->fresh() ?? $rule;
    }

    public function deactivate(CountScheduleRule $rule): CountScheduleRule
    {
        $rule->update(['is_active' => false]);

        return $rule->fresh() ?? $rule;
    }

    private function assertScope(CountScopeType $scopeType, ?int $scopeId): void
    {
        if ($scopeType !== CountScopeType::Full && $scopeId === null) {
            throw ValidationException::withMessages([
                'scope_id' => __('Scope ID is required for zone or category counts.'),
            ]);
        }
    }
}
