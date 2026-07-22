<?php

declare(strict_types=1);

namespace App\Repositories\Eloquent;

use App\Models\HolidayCalendar;
use App\Models\HolidayCalendarAssignment;
use App\Models\HolidayDate;
use App\Repositories\Contracts\HolidayCalendarRepositoryInterface;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Support\Collection;

final class HolidayCalendarRepository implements HolidayCalendarRepositoryInterface
{
    /**
     * @param  array<string, mixed>  $filters
     * @param  list<int>|null  $accessibleBranchIds
     */
    public function paginate(array $filters, int $perPage, ?array $accessibleBranchIds = null): LengthAwarePaginator
    {
        $sort = in_array($filters['sort'] ?? '', ['name', 'code', 'status'], true)
            ? $filters['sort']
            : 'name';
        $direction = ($filters['direction'] ?? 'asc') === 'desc' ? 'desc' : 'asc';

        return HolidayCalendar::query()
            ->with(['legalEntity:id,legal_name', 'branch:id,name'])
            ->when($filters['search'] ?? null, function ($q, string $search): void {
                $q->where(function ($inner) use ($search): void {
                    $inner->where('name', 'like', "%{$search}%")
                        ->orWhere('code', 'like', "%{$search}%");
                });
            })
            ->when($filters['status'] ?? null, fn ($q, string $status) => $q->where('status', $status))
            ->when($accessibleBranchIds !== null, fn ($q) => $q->where(function ($inner) use ($accessibleBranchIds): void {
                $inner->whereNull('branch_id')->orWhereIn('branch_id', $accessibleBranchIds);
            }))
            ->orderBy($sort, $direction)
            ->paginate($perPage)
            ->withQueryString();
    }

    public function findWithDetails(HolidayCalendar $calendar): HolidayCalendar
    {
        return $calendar->load([
            'legalEntity:id,legal_name',
            'branch:id,name',
            'dates' => fn ($q) => $q->orderBy('holiday_date'),
            'assignments.assignable',
        ]);
    }

    public function create(array $attributes): HolidayCalendar
    {
        return HolidayCalendar::query()->create($attributes);
    }

    public function update(HolidayCalendar $calendar, array $attributes): HolidayCalendar
    {
        $calendar->update($attributes);

        return $calendar->fresh(['legalEntity', 'branch']) ?? $calendar;
    }

    public function addDate(HolidayCalendar $calendar, array $attributes): HolidayDate
    {
        return $calendar->dates()->create($attributes);
    }

    public function deleteDate(HolidayDate $date): void
    {
        $date->delete();
    }

    public function createAssignment(array $attributes): HolidayCalendarAssignment
    {
        return HolidayCalendarAssignment::query()->create($attributes);
    }

    public function deleteAssignment(HolidayCalendarAssignment $assignment): void
    {
        $assignment->delete();
    }

    public function activeAssignmentsFor(string $assignableType, int $assignableId, string $asOf): Collection
    {
        return HolidayCalendarAssignment::query()
            ->with('calendar')
            ->where('assignable_type', $assignableType)
            ->where('assignable_id', $assignableId)
            ->where('status', 'active')
            ->where('effective_from', '<=', $asOf)
            ->where(function ($q) use ($asOf): void {
                $q->whereNull('effective_to')->orWhere('effective_to', '>=', $asOf);
            })
            ->get();
    }
}
