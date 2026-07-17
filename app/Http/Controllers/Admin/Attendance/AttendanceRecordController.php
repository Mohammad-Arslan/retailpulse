<?php

declare(strict_types=1);

namespace App\Http\Controllers\Admin\Attendance;

use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\Attendance\StoreManualAttendanceRequest;
use App\Models\AttendanceRecord;
use App\Models\Branch;
use App\Models\Employee;
use App\Services\Attendance\AttendanceClockPayload;
use App\Services\Attendance\AttendanceService;
use App\Support\ListPagination;
use Carbon\CarbonImmutable;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

final class AttendanceRecordController extends Controller
{
    public function __construct(
        private readonly AttendanceService $attendanceService,
    ) {}

    public function index(Request $request): Response
    {
        $this->authorize('viewAny', AttendanceRecord::class);

        $filters = ListPagination::filters($request, ['search', 'status', 'sort', 'direction']);
        $perPage = ListPagination::resolve($filters['per_page']);

        $query = AttendanceRecord::query()
            ->with([
                'employee:id,first_name,last_name,employee_code',
                'branch:id,name',
                'source:id,name,driver',
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
            ->orderBy($filters['sort'] ?? 'clock_in', $filters['direction'] ?? 'desc');

        $records = $query->paginate($perPage)->withQueryString();

        return Inertia::render('Admin/Attendance/Records/Index', [
            'records' => $records->through(fn (AttendanceRecord $record) => [
                'id' => $record->id,
                'employee' => $record->employee?->fullName(),
                'employee_code' => $record->employee?->employee_code,
                'branch' => $record->branch?->name,
                'source' => $record->source?->name,
                'driver' => $record->source?->driver,
                'clock_in' => $record->clock_in?->toDateTimeString(),
                'clock_out' => $record->clock_out?->toDateTimeString(),
                'worked_minutes' => $record->worked_minutes,
                'status' => $record->status,
                'is_historical' => $record->is_historical,
            ]),
            'filters' => $filters,
        ]);
    }

    public function create(): Response
    {
        $this->authorize('create', AttendanceRecord::class);

        return Inertia::render('Admin/Attendance/Records/Create', [
            'employees' => Employee::query()
                ->where('status', 'active')
                ->orderBy('first_name')
                ->get(['id', 'first_name', 'last_name', 'employee_code']),
            'branches' => Branch::query()->where('is_active', true)->orderBy('name')->get(['id', 'name', 'code']),
            'actions' => ['clock_in', 'clock_out'],
        ]);
    }

    public function store(StoreManualAttendanceRequest $request): RedirectResponse
    {
        $this->authorize('create', AttendanceRecord::class);

        $data = $request->validated();
        $source = $this->attendanceService->resolveActiveSource('manual', (int) $data['branch_id']);
        $at = isset($data['clocked_at']) ? CarbonImmutable::parse($data['clocked_at']) : null;

        $payload = new AttendanceClockPayload(
            employeeId: (int) $data['employee_id'],
            branchId: (int) $data['branch_id'],
            at: $at,
            openRecordId: isset($data['open_record_id']) ? (int) $data['open_record_id'] : null,
        );

        if ($data['action'] === 'clock_in') {
            $this->attendanceService->clockIn($source, $payload);
        } else {
            $this->attendanceService->clockOut($source, $payload);
        }

        return redirect()
            ->route('admin.attendance.records.index')
            ->with('success', __('Attendance Record Saved Successfully.'));
    }
}
