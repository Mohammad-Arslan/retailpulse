<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[Fillable([
    'legal_entity_id',
    'default_holiday_calendar_id',
    'employee_code_sequence_key',
    'settings_json',
])]
class HrEntitySetting extends Model
{
    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'settings_json' => 'array',
        ];
    }

    public function legalEntity(): BelongsTo
    {
        return $this->belongsTo(OrganizationEntity::class, 'legal_entity_id');
    }

    public function defaultHolidayCalendar(): BelongsTo
    {
        return $this->belongsTo(HolidayCalendar::class, 'default_holiday_calendar_id');
    }
}
