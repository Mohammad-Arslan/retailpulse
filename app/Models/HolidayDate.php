<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[Fillable([
    'holiday_calendar_id',
    'holiday_date',
    'name',
    'holiday_type',
    'is_paid',
    'is_recurring',
    'recurrence_month',
    'recurrence_day',
])]
class HolidayDate extends Model
{
    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'holiday_date' => 'date',
            'is_paid' => 'boolean',
            'is_recurring' => 'boolean',
            'recurrence_month' => 'integer',
            'recurrence_day' => 'integer',
        ];
    }

    public function calendar(): BelongsTo
    {
        return $this->belongsTo(HolidayCalendar::class, 'holiday_calendar_id');
    }
}
