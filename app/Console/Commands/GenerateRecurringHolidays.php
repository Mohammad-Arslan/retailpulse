<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Models\HolidayDate;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Date;

final class GenerateRecurringHolidays extends Command
{
    protected $signature = 'hr:generate-recurring-holidays {year : Target calendar year}';

    protected $description = 'Generate dated holiday rows from recurring holiday patterns for the target year';

    public function handle(): int
    {
        $year = (int) $this->argument('year');

        if ($year < 2000 || $year > 2100) {
            $this->error('Year must be between 2000 and 2100.');

            return self::FAILURE;
        }

        $patterns = HolidayDate::query()
            ->where('is_recurring', true)
            ->whereNotNull('recurrence_month')
            ->whereNotNull('recurrence_day')
            ->get();

        if ($patterns->isEmpty()) {
            $this->info('No recurring holiday patterns found.');

            return self::SUCCESS;
        }

        $created = 0;

        foreach ($patterns as $pattern) {
            $dateString = sprintf(
                '%04d-%02d-%02d',
                $year,
                (int) $pattern->recurrence_month,
                (int) $pattern->recurrence_day,
            );

            if (! $this->isValidDate($dateString)) {
                $this->warn("Skipping invalid recurrence date {$dateString} for pattern #{$pattern->id}.");

                continue;
            }

            $exists = HolidayDate::query()
                ->where('holiday_calendar_id', $pattern->holiday_calendar_id)
                ->whereDate('holiday_date', $dateString)
                ->exists();

            if ($exists) {
                continue;
            }

            HolidayDate::query()->create([
                'holiday_calendar_id' => $pattern->holiday_calendar_id,
                'holiday_date' => $dateString,
                'name' => $pattern->name,
                'holiday_type' => $pattern->holiday_type,
                'is_paid' => $pattern->is_paid,
                'is_recurring' => false,
                'recurrence_month' => null,
                'recurrence_day' => null,
            ]);

            $created++;
        }

        $this->info("Generated {$created} holiday date(s) for {$year}.");

        return self::SUCCESS;
    }

    private function isValidDate(string $dateString): bool
    {
        $date = Date::createFromFormat('Y-m-d', $dateString);

        return $date !== false && $date->format('Y-m-d') === $dateString;
    }
}
