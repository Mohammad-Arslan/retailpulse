<?php

declare(strict_types=1);

namespace App\Http\Controllers\Admin\Leave;

use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\Leave\RescheduleLeaveRequestRequest;
use App\Http\Requests\Admin\Leave\StoreLeaveRequestRequest;
use App\Models\Employee;
use App\Models\LeaveRequest;
use App\Models\LeaveType;
use App\Services\Leave\LeaveService;
use App\Support\ListPagination;
use Carbon\CarbonImmutable;
use DomainException;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;
use Inertia\Inertia;
use Inertia\Response;

final class LeaveRequestController extends Controller
{
    public function __construct(
        private readonly LeaveService $leaveService,
    ) {}

    public function index(Request $request): Response
    {
        $this->authorize('viewAny', LeaveRequest::class);

        $filters = ListPagination::filters($request, ['search', 'status', 'sort', 'direction']);
        $perPage = ListPagination::resolve($filters['per_page']);

        $query = LeaveRequest::query()
            ->with([
                'employee:id,first_name,last_name,employee_code',
                'leaveType:id,code,name,is_paid',
                'reschedules' => fn ($q) => $q->orderByDesc('created_at'),
            ])
            ->when($filters['search'] ?? null, function ($q, string $search): void {
                $q->where(function ($inner) use ($search): void {
                    $inner->whereHas('employee', function ($employee) use ($search): void {
                        $employee->where('first_name', 'like', "%{$search}%")
                            ->orWhere('last_name', 'like', "%{$search}%")
                            ->orWhere('employee_code', 'like', "%{$search}%");
                    });
                });
            })
            ->when($filters['status'] ?? null, fn ($q, string $status) => $q->where('status', $status))
            ->orderBy($filters['sort'] ?? 'start_date', $filters['direction'] ?? 'desc');

        $requests = $query->paginate($perPage)->withQueryString();

        return Inertia::render('Admin/Leave/Requests/Index', [
            'requests' => $requests->through(fn (LeaveRequest $leaveRequest) => [
                'id' => $leaveRequest->id,
                'employee' => $leaveRequest->employee?->fullName(),
                'employee_code' => $leaveRequest->employee?->employee_code,
                'leave_type' => $leaveRequest->leaveType?->name,
                'leave_type_code' => $leaveRequest->leaveType?->code,
                'is_paid' => $leaveRequest->leaveType?->is_paid,
                'start_date' => $leaveRequest->start_date?->toDateString(),
                'end_date' => $leaveRequest->end_date?->toDateString(),
                'duration_type' => $leaveRequest->duration_type,
                'session' => $leaveRequest->session,
                'start_time' => $leaveRequest->start_time,
                'end_time' => $leaveRequest->end_time,
                'days' => (float) $leaveRequest->days,
                'deduct_from_balance' => $leaveRequest->deduct_from_balance,
                'reason' => $leaveRequest->reason,
                'status' => $leaveRequest->status,
                'reschedule_count' => $leaveRequest->reschedules->count(),
            ]),
            'filters' => $filters,
        ]);
    }

    public function create(): Response
    {
        $this->authorize('create', LeaveRequest::class);

        return Inertia::render('Admin/Leave/Requests/Create', [
            'employees' => Employee::query()
                ->where('status', 'active')
                ->orderBy('first_name')
                ->get(['id', 'first_name', 'last_name', 'employee_code']),
            'leaveTypes' => LeaveType::query()
                ->where('status', 'active')
                ->orderBy('name')
                ->get(['id', 'code', 'name', 'is_paid']),
        ]);
    }

    public function store(StoreLeaveRequestRequest $request): RedirectResponse
    {
        $this->authorize('create', LeaveRequest::class);

        $data = $request->validated();
        $employee = Employee::query()->findOrFail((int) $data['employee_id']);
        $leaveType = LeaveType::query()->findOrFail((int) $data['leave_type_id']);

        $this->leaveService->requestLeave(
            employee: $employee,
            leaveType: $leaveType,
            startDate: CarbonImmutable::parse($data['start_date']),
            endDate: CarbonImmutable::parse($data['end_date']),
            reason: $data['reason'] ?? null,
            durationType: $data['duration_type'] ?? 'full_day',
            session: $data['session'] ?? null,
            startTime: $data['start_time'] ?? null,
            endTime: $data['end_time'] ?? null,
        );

        return redirect()
            ->route('admin.leave.requests.index')
            ->with('success', __('Leave Request Submitted Successfully.'));
    }

    public function approve(Request $request, LeaveRequest $leaveRequest): RedirectResponse
    {
        $this->authorize('approve', $leaveRequest);

        try {
            $this->leaveService->approve($leaveRequest, (int) $request->user()->id);
        } catch (DomainException $e) {
            return back()->with('error', $e->getMessage());
        }

        return back()->with('success', __('Leave Request Approved Successfully.'));
    }

    public function reject(Request $request, LeaveRequest $leaveRequest): RedirectResponse
    {
        $this->authorize('reject', $leaveRequest);

        try {
            $this->leaveService->reject($leaveRequest, (int) $request->user()->id);
        } catch (DomainException $e) {
            return back()->with('error', $e->getMessage());
        }

        return back()->with('success', __('Leave Request Rejected Successfully.'));
    }

    public function reschedule(RescheduleLeaveRequestRequest $request, LeaveRequest $leaveRequest): RedirectResponse
    {
        $this->authorize('reschedule', $leaveRequest);

        $data = $request->validated();

        try {
            $this->leaveService->reschedule(
                request: $leaveRequest,
                newStartDate: CarbonImmutable::parse($data['new_start_date']),
                newEndDate: CarbonImmutable::parse($data['new_end_date']),
                changedByUserId: (int) $request->user()->id,
                reason: $data['reason'] ?? null,
            );
        } catch (ValidationException $e) {
            return back()->with('error', collect($e->errors())->flatten()->first());
        } catch (DomainException $e) {
            return back()->with('error', $e->getMessage());
        }

        return back()->with('success', __('Leave Request Rescheduled Successfully.'));
    }
}
