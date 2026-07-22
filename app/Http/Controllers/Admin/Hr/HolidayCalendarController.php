<?php

declare(strict_types=1);

namespace App\Http\Controllers\Admin\Hr;

use App\DTOs\Hr\CreateHolidayCalendarAssignmentData;
use App\DTOs\Hr\CreateHolidayCalendarData;
use App\DTOs\Hr\CreateHolidayDateData;
use App\DTOs\Hr\UpdateHolidayCalendarData;
use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\Hr\StoreHolidayCalendarAssignmentRequest;
use App\Http\Requests\Admin\Hr\StoreHolidayCalendarRequest;
use App\Http\Requests\Admin\Hr\StoreHolidayDateRequest;
use App\Http\Requests\Admin\Hr\UpdateHolidayCalendarRequest;
use App\Models\HolidayCalendar;
use App\Models\HolidayCalendarAssignment;
use App\Models\HolidayDate;
use App\Services\BranchContextService;
use App\Services\Hr\HolidayCalendarService;
use App\Support\ListPagination;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

final class HolidayCalendarController extends Controller
{
    public function __construct(
        private readonly HolidayCalendarService $calendars,
        private readonly BranchContextService $branchContext,
    ) {}

    public function index(Request $request): Response
    {
        $this->authorize('viewAny', HolidayCalendar::class);

        $filters = ListPagination::filters($request, ['search', 'status', 'sort', 'direction']);

        return Inertia::render(
            'Admin/Hr/HolidayCalendars/Index',
            $this->calendars->indexPayload(
                $filters,
                ListPagination::resolve($filters['per_page']),
                $this->branchContext->accessibleBranchIds($request->user()),
            ),
        );
    }

    public function store(StoreHolidayCalendarRequest $request): RedirectResponse
    {
        $this->authorize('create', HolidayCalendar::class);

        $calendar = $this->calendars->createCalendar(CreateHolidayCalendarData::fromRequest($request));

        return redirect()
            ->route('admin.hr.holiday-calendars.show', $calendar)
            ->with('success', __('Holiday Calendar Created Successfully.'));
    }

    public function show(Request $request, HolidayCalendar $holidayCalendar): Response
    {
        $this->authorize('view', $holidayCalendar);

        return Inertia::render(
            'Admin/Hr/HolidayCalendars/Show',
            $this->calendars->showPayload($holidayCalendar, $this->branchContext->accessibleBranchIds($request->user())),
        );
    }

    public function update(UpdateHolidayCalendarRequest $request, HolidayCalendar $holidayCalendar): RedirectResponse
    {
        $this->authorize('update', $holidayCalendar);

        $this->calendars->updateCalendar($holidayCalendar, UpdateHolidayCalendarData::fromRequest($request));

        return back()->with('success', __('Holiday Calendar Updated Successfully.'));
    }

    public function storeDate(StoreHolidayDateRequest $request, HolidayCalendar $holidayCalendar): RedirectResponse
    {
        $this->authorize('update', $holidayCalendar);

        $this->calendars->addDate($holidayCalendar, CreateHolidayDateData::fromRequest($request));

        return back()->with('success', __('Holiday Date Added Successfully.'));
    }

    public function destroyDate(HolidayCalendar $holidayCalendar, HolidayDate $holidayDate): RedirectResponse
    {
        $this->authorize('update', $holidayCalendar);

        if ($holidayDate->holiday_calendar_id !== $holidayCalendar->id) {
            abort(404);
        }

        $this->calendars->deleteDate($holidayDate);

        return back()->with('success', __('Holiday Date Removed Successfully.'));
    }

    public function storeAssignment(StoreHolidayCalendarAssignmentRequest $request, HolidayCalendar $holidayCalendar): RedirectResponse
    {
        $this->authorize('update', $holidayCalendar);

        $this->calendars->assignCalendar(
            CreateHolidayCalendarAssignmentData::fromRequest($request, $holidayCalendar->id),
        );

        return back()->with('success', __('Calendar Assignment Created Successfully.'));
    }

    public function destroyAssignment(HolidayCalendar $holidayCalendar, HolidayCalendarAssignment $assignment): RedirectResponse
    {
        $this->authorize('update', $holidayCalendar);

        if ($assignment->holiday_calendar_id !== $holidayCalendar->id) {
            abort(404);
        }

        $this->calendars->deleteAssignment($assignment);

        return back()->with('success', __('Calendar Assignment Removed Successfully.'));
    }
}
