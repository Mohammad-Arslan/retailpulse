<?php

declare(strict_types=1);

namespace App\Services\Hr;

use App\DTOs\Hr\CreateHolidayCalendarAssignmentData;
use App\DTOs\Hr\CreateHolidayCalendarData;
use App\DTOs\Hr\CreateHolidayDateData;
use App\DTOs\Hr\UpdateHolidayCalendarData;
use App\Models\Branch;
use App\Models\Employee;
use App\Models\HolidayCalendar;
use App\Models\HolidayCalendarAssignment;
use App\Models\HolidayDate;
use App\Models\OrganizationEntity;
use App\Repositories\Contracts\HolidayCalendarRepositoryInterface;
use App\Services\Accounting\DocumentNumberService;
use App\Services\Hr\Concerns\GeneratesHrMasterCodes;
use App\Support\HolidayCalendarPresenter;
use Carbon\CarbonInterface;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Support\Facades\DB;

final class HolidayCalendarService
{
    use GeneratesHrMasterCodes;

    private const CODE_TYPE = 'holiday_calendar';

    private const CODE_PREFIX = 'HOL';

    public function __construct(
        private readonly HolidayCalendarRepositoryInterface $calendars,
        private readonly DocumentNumberService $documentNumberService,
    ) {}

    protected function documentNumbers(): DocumentNumberService
    {
        return $this->documentNumberService;
    }

    /**
     * @param  array<string, mixed>  $filters
     * @return array{
     *     calendars: LengthAwarePaginator,
     *     filters: array<string, mixed>,
     *     legalEntities: \Illuminate\Support\Collection,
     *     branches: \Illuminate\Support\Collection,
     *     nextCode: string
     * }
     */
    public function indexPayload(array $filters, int $perPage): array
    {
        return [
            'calendars' => HolidayCalendarPresenter::paginated($this->paginate($filters, $perPage)),
            'filters' => $filters,
            'legalEntities' => $this->activeLegalEntities(),
            'branches' => $this->activeBranches(),
            'nextCode' => $this->peekMasterCode(self::CODE_TYPE, self::CODE_PREFIX),
        ];
    }

    /**
     * @return array{
     *     calendar: array<string, mixed>,
     *     dates: list<array<string, mixed>>,
     *     assignments: list<array<string, mixed>>,
     *     legalEntities: \Illuminate\Support\Collection,
     *     branches: \Illuminate\Support\Collection,
     *     employees: \Illuminate\Support\Collection
     * }
     */
    public function showPayload(HolidayCalendar $calendar): array
    {
        $calendar = $this->details($calendar);

        return [
            'calendar' => HolidayCalendarPresenter::detail($calendar),
            'dates' => $calendar->dates->map(fn (HolidayDate $d) => HolidayCalendarPresenter::dateItem($d))->values()->all(),
            'assignments' => $calendar->assignments
                ->map(fn (HolidayCalendarAssignment $a) => HolidayCalendarPresenter::assignmentItem($a))
                ->values()
                ->all(),
            'legalEntities' => $this->activeLegalEntities(),
            'branches' => $this->activeBranches(),
            'employees' => Employee::query()
                ->where('status', 'active')
                ->orderBy('first_name')
                ->limit(500)
                ->get(['id', 'first_name', 'last_name', 'employee_code']),
        ];
    }

    /**
     * @param  array<string, mixed>  $filters
     */
    public function paginate(array $filters, int $perPage): LengthAwarePaginator
    {
        return $this->calendars->paginate($filters, $perPage);
    }

    public function details(HolidayCalendar $calendar): HolidayCalendar
    {
        return $this->calendars->findWithDetails($calendar);
    }

    public function createCalendar(CreateHolidayCalendarData $data): HolidayCalendar
    {
        return DB::transaction(function () use ($data): HolidayCalendar {
            $attributes = $data->toArray();
            $attributes['code'] = $this->nextMasterCode(self::CODE_TYPE, self::CODE_PREFIX);

            return $this->calendars->create($attributes);
        });
    }

    public function updateCalendar(HolidayCalendar $calendar, UpdateHolidayCalendarData $data): HolidayCalendar
    {
        return $this->calendars->update($calendar, $data->toArray());
    }

    public function addDate(HolidayCalendar $calendar, CreateHolidayDateData $data): HolidayDate
    {
        return $this->calendars->addDate($calendar, $data->toArray());
    }

    public function deleteDate(HolidayDate $date): void
    {
        $this->calendars->deleteDate($date);
    }

    public function assignCalendar(CreateHolidayCalendarAssignmentData $data): HolidayCalendarAssignment
    {
        return $this->calendars->createAssignment($data->toArray());
    }

    public function deleteAssignment(HolidayCalendarAssignment $assignment): void
    {
        $this->calendars->deleteAssignment($assignment);
    }

    /**
     * @return list<array{calendar: HolidayCalendar, assignment: HolidayCalendarAssignment|null, priority: int}>
     */
    public function resolveCalendarsForEmployee(Employee $employee, ?CarbonInterface $date = null): array
    {
        $asOf = ($date ?? now())->toDateString();
        $resolved = [];

        $this->appendAssignmentMatches($resolved, Employee::class, $employee->id, $asOf, 100);
        $this->appendAssignmentMatches($resolved, Branch::class, $employee->primary_branch_id, $asOf, 50);
        $this->appendAssignmentMatches($resolved, OrganizationEntity::class, $employee->legal_entity_id, $asOf, 10);

        usort($resolved, fn (array $a, array $b): int => ($b['priority'] ?? 0) <=> ($a['priority'] ?? 0));

        return $resolved;
    }

    /**
     * @return \Illuminate\Support\Collection<int, OrganizationEntity>
     */
    private function activeLegalEntities()
    {
        return OrganizationEntity::query()
            ->where('status', 'active')
            ->orderBy('legal_name')
            ->get(['id', 'legal_name']);
    }

    /**
     * @return \Illuminate\Support\Collection<int, Branch>
     */
    private function activeBranches()
    {
        return Branch::query()
            ->where('is_active', true)
            ->orderBy('name')
            ->get(['id', 'name', 'code']);
    }

    /**
     * @param  list<array<string, mixed>>  $resolved
     */
    private function appendAssignmentMatches(array &$resolved, string $type, ?int $id, string $asOf, int $defaultPriority): void
    {
        if ($id === null) {
            return;
        }

        foreach ($this->calendars->activeAssignmentsFor($type, $id, $asOf) as $assignment) {
            if ($assignment->calendar === null || $assignment->calendar->status !== 'active') {
                continue;
            }

            $resolved[] = [
                'calendar' => $assignment->calendar,
                'assignment' => $assignment,
                'priority' => $assignment->priority > 0 ? $assignment->priority : $defaultPriority,
            ];
        }
    }
}
