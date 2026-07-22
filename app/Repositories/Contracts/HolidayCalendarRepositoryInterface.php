<?php

declare(strict_types=1);

namespace App\Repositories\Contracts;

use App\Models\HolidayCalendar;
use App\Models\HolidayCalendarAssignment;
use App\Models\HolidayDate;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Support\Collection;

interface HolidayCalendarRepositoryInterface
{
    /**
     * @param  array<string, mixed>  $filters
     * @param  list<int>|null  $accessibleBranchIds
     */
    public function paginate(array $filters, int $perPage, ?array $accessibleBranchIds = null): LengthAwarePaginator;

    public function findWithDetails(HolidayCalendar $calendar): HolidayCalendar;

    public function create(array $attributes): HolidayCalendar;

    public function update(HolidayCalendar $calendar, array $attributes): HolidayCalendar;

    public function addDate(HolidayCalendar $calendar, array $attributes): HolidayDate;

    public function deleteDate(HolidayDate $date): void;

    public function createAssignment(array $attributes): HolidayCalendarAssignment;

    public function deleteAssignment(HolidayCalendarAssignment $assignment): void;

    /**
     * @return Collection<int, HolidayCalendarAssignment>
     */
    public function activeAssignmentsFor(string $assignableType, int $assignableId, string $asOf): Collection;
}
