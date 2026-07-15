<?php

declare(strict_types=1);

namespace App\Support;

use App\Models\Branch;
use App\Models\Employee;
use App\Models\HolidayCalendar;
use App\Models\HolidayCalendarAssignment;
use App\Models\HolidayDate;
use App\Models\OrganizationEntity;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;

final class HolidayCalendarPresenter
{
    /**
     * @return array<string, mixed>
     */
    public static function listItem(HolidayCalendar $calendar): array
    {
        return [
            'id' => $calendar->id,
            'code' => $calendar->code,
            'name' => $calendar->name,
            'legal_entity_id' => $calendar->legal_entity_id,
            'legal_entity_name' => $calendar->legalEntity?->legal_name,
            'branch_id' => $calendar->branch_id,
            'branch_name' => $calendar->branch?->name,
            'status' => $calendar->status,
        ];
    }

    public static function paginated(LengthAwarePaginator $paginator): LengthAwarePaginator
    {
        return $paginator->through(fn (HolidayCalendar $calendar) => self::listItem($calendar));
    }

    /**
     * @return array<string, mixed>
     */
    public static function detail(HolidayCalendar $calendar): array
    {
        return self::listItem($calendar);
    }

    /**
     * @return array<string, mixed>
     */
    public static function dateItem(HolidayDate $date): array
    {
        return [
            'id' => $date->id,
            'holiday_date' => $date->holiday_date->toDateString(),
            'name' => $date->name,
            'holiday_type' => $date->holiday_type,
            'is_paid' => $date->is_paid,
        ];
    }

    /**
     * @return array<string, mixed>
     */
    public static function assignmentItem(HolidayCalendarAssignment $assignment): array
    {
        $assignable = $assignment->relationLoaded('assignable')
            ? $assignment->assignable
            : $assignment->assignable()->first();

        $label = match (true) {
            $assignable instanceof Employee => $assignable->fullName(),
            $assignable instanceof Branch => $assignable->name,
            $assignable instanceof OrganizationEntity => $assignable->legal_name,
            default => (string) $assignment->assignable_id,
        };

        return [
            'id' => $assignment->id,
            'assignable_type' => match ($assignment->assignable_type) {
                Employee::class => 'employee',
                Branch::class => 'branch',
                OrganizationEntity::class => 'legal_entity',
                default => $assignment->assignable_type,
            },
            'assignable_id' => $assignment->assignable_id,
            'assignable_label' => $label,
            'effective_from' => $assignment->effective_from->toDateString(),
            'effective_to' => $assignment->effective_to?->toDateString(),
            'priority' => $assignment->priority,
            'status' => $assignment->status,
        ];
    }
}
