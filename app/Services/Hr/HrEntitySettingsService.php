<?php

declare(strict_types=1);

namespace App\Services\Hr;

use App\Models\HolidayCalendar;
use App\Models\HrEntitySetting;
use App\Models\OrganizationEntity;

final class HrEntitySettingsService
{
    /**
     * @return array<string, mixed>
     */
    public function indexPayload(): array
    {
        $entities = OrganizationEntity::query()
            ->where('status', 'active')
            ->orderBy('legal_name')
            ->get(['id', 'legal_name']);

        $settings = HrEntitySetting::query()
            ->with('defaultHolidayCalendar:id,code,name')
            ->get()
            ->keyBy('legal_entity_id');

        $rows = $entities->map(function (OrganizationEntity $entity) use ($settings): array {
            $setting = $settings->get($entity->id);

            return [
                'legal_entity_id' => $entity->id,
                'legal_entity_name' => $entity->legal_name,
                'default_holiday_calendar_id' => $setting?->default_holiday_calendar_id,
                'default_holiday_calendar' => $setting?->defaultHolidayCalendar?->only(['id', 'code', 'name']),
                'employee_code_sequence_key' => $setting?->employee_code_sequence_key,
                'settings_json' => $setting?->settings_json ?? [],
            ];
        });

        return [
            'entities' => $rows,
            'holidayCalendars' => HolidayCalendar::query()
                ->where('status', 'active')
                ->orderBy('name')
                ->get(['id', 'code', 'name']),
        ];
    }

    /**
     * @param  array<string, mixed>  $attributes
     */
    public function upsert(int $legalEntityId, array $attributes): HrEntitySetting
    {
        return HrEntitySetting::query()->updateOrCreate(
            ['legal_entity_id' => $legalEntityId],
            [
                'default_holiday_calendar_id' => $attributes['default_holiday_calendar_id'] ?? null,
                'employee_code_sequence_key' => $attributes['employee_code_sequence_key'] ?? null,
                'settings_json' => $attributes['settings_json'] ?? [],
            ],
        );
    }

    public function forEntity(int $legalEntityId): ?HrEntitySetting
    {
        return HrEntitySetting::query()
            ->where('legal_entity_id', $legalEntityId)
            ->first();
    }
}
