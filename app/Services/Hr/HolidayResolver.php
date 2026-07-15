<?php

declare(strict_types=1);

namespace App\Services\Hr;

use App\Models\Employee;
use App\Models\HolidayDate;
use Carbon\CarbonInterface;

final class HolidayResolver
{
    public function __construct(
        private readonly HolidayCalendarService $calendars,
    ) {}

    public function isHoliday(Employee $employee, CarbonInterface $date): ?HolidayDate
    {
        $resolved = $this->calendars->resolveCalendarsForEmployee($employee, $date);

        foreach ($resolved as $entry) {
            $calendar = $entry['calendar'];
            $holiday = HolidayDate::query()
                ->where('holiday_calendar_id', $calendar->id)
                ->whereDate('holiday_date', $date->toDateString())
                ->first();

            if ($holiday !== null) {
                return $holiday;
            }
        }

        return null;
    }

    public function isPublicHoliday(Employee $employee, CarbonInterface $date): bool
    {
        $holiday = $this->isHoliday($employee, $date);

        return $holiday !== null && $holiday->holiday_type === 'public';
    }
}
