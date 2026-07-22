<?php

declare(strict_types=1);

namespace App\Http\Controllers\Admin\Leave;

use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\Leave\StoreLeaveEncashmentRequest;
use App\Models\Employee;
use App\Models\LeaveEncashment;
use App\Models\LeaveType;
use App\Services\BranchContextService;
use App\Services\Leave\LeaveEncashmentService;
use App\Support\BranchScope;
use App\Support\ListPagination;
use DomainException;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;
use Inertia\Inertia;
use Inertia\Response;

final class LeaveEncashmentController extends Controller
{
    public function __construct(
        private readonly LeaveEncashmentService $encashmentService,
        private readonly BranchContextService $branchContext,
    ) {}

    public function index(Request $request): Response
    {
        $this->authorize('viewAny', LeaveEncashment::class);

        $filters = ListPagination::filters($request, ['search', 'status', 'sort', 'direction']);
        $perPage = ListPagination::resolve($filters['per_page']);

        $query = LeaveEncashment::query()
            ->with([
                'employee:id,first_name,last_name,employee_code',
                'leaveType:id,code,name',
            ])
            ->when($filters['search'] ?? null, function ($q, string $search): void {
                $q->whereHas('employee', function ($employee) use ($search): void {
                    $employee->where('first_name', 'like', "%{$search}%")
                        ->orWhere('last_name', 'like', "%{$search}%")
                        ->orWhere('employee_code', 'like', "%{$search}%");
                });
            })
            ->when($filters['status'] ?? null, fn ($q, string $status) => $q->where('status', $status))
            ->orderBy($filters['sort'] ?? 'created_at', $filters['direction'] ?? 'desc');

        BranchScope::applyViaEmployee($query, $this->branchContext->accessibleBranchIds($request->user()));

        $encashments = $query->paginate($perPage)->withQueryString();

        return Inertia::render('Admin/Leave/Encashments/Index', [
            'encashments' => $encashments->through(fn (LeaveEncashment $encashment) => [
                'id' => $encashment->id,
                'employee' => $encashment->employee?->fullName(),
                'employee_code' => $encashment->employee?->employee_code,
                'leave_type' => $encashment->leaveType?->name,
                'leave_type_code' => $encashment->leaveType?->code,
                'days' => (float) $encashment->days,
                'reason' => $encashment->reason,
                'status' => $encashment->status,
                'created_at' => $encashment->created_at?->toDateString(),
            ]),
            'filters' => $filters,
        ]);
    }

    public function create(Request $request): Response
    {
        $this->authorize('create', LeaveEncashment::class);

        $accessibleBranchIds = $this->branchContext->accessibleBranchIds($request->user());

        return Inertia::render('Admin/Leave/Encashments/Create', [
            'employees' => Employee::query()
                ->where('status', 'active')
                ->when($accessibleBranchIds !== null, fn ($q) => $q->whereIn('primary_branch_id', $accessibleBranchIds))
                ->orderBy('first_name')
                ->get(['id', 'first_name', 'last_name', 'employee_code']),
            'leaveTypes' => LeaveType::query()
                ->where('status', 'active')
                ->orderBy('name')
                ->get(['id', 'code', 'name']),
        ]);
    }

    public function store(StoreLeaveEncashmentRequest $request): RedirectResponse
    {
        $this->authorize('create', LeaveEncashment::class);

        $data = $request->validated();
        $employee = Employee::query()->findOrFail((int) $data['employee_id']);
        $leaveType = LeaveType::query()->findOrFail((int) $data['leave_type_id']);

        try {
            $this->encashmentService->requestEncashment(
                employee: $employee,
                leaveType: $leaveType,
                days: (float) $data['days'],
                reason: $data['reason'] ?? null,
            );
        } catch (DomainException $e) {
            return back()->withErrors(['days' => $e->getMessage()])->withInput();
        }

        return redirect()
            ->route('admin.leave.encashments.index')
            ->with('success', __('Leave Encashment Requested Successfully.'));
    }

    public function approve(Request $request, LeaveEncashment $leaveEncashment): RedirectResponse
    {
        $this->authorize('approve', $leaveEncashment);

        try {
            $this->encashmentService->approve($leaveEncashment, (int) $request->user()->id);
        } catch (DomainException|ValidationException $e) {
            return back()->with('error', $e->getMessage());
        }

        return back()->with('success', __('Leave Encashment Approved Successfully.'));
    }

    public function reject(Request $request, LeaveEncashment $leaveEncashment): RedirectResponse
    {
        $this->authorize('reject', $leaveEncashment);

        try {
            $this->encashmentService->reject($leaveEncashment, (int) $request->user()->id);
        } catch (DomainException|ValidationException $e) {
            return back()->with('error', $e->getMessage());
        }

        return back()->with('success', __('Leave Encashment Rejected Successfully.'));
    }

    public function cancel(Request $request, LeaveEncashment $leaveEncashment): RedirectResponse
    {
        $this->authorize('cancel', $leaveEncashment);

        try {
            $this->encashmentService->cancel($leaveEncashment, (int) $request->user()->id);
        } catch (DomainException|ValidationException $e) {
            return back()->with('error', $e->getMessage());
        }

        return back()->with('success', __('Leave Encashment Cancelled Successfully.'));
    }
}
