<?php

declare(strict_types=1);

namespace App\Http\Controllers\Admin\Overtime;

use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\Overtime\StoreToilCashClaimRequest;
use App\Models\Employee;
use App\Models\LeaveType;
use App\Models\ToilClaim;
use App\Services\BranchContextService;
use App\Services\Overtime\ToilClaimService;
use App\Support\BranchScope;
use App\Support\ListPagination;
use DomainException;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;
use Inertia\Inertia;
use Inertia\Response;

final class ToilCashClaimController extends Controller
{
    public function __construct(
        private readonly ToilClaimService $toilClaims,
        private readonly BranchContextService $branchContext,
    ) {}

    public function index(Request $request): Response
    {
        $this->authorize('viewAny', ToilClaim::class);

        $filters = ListPagination::filters($request, ['search', 'status', 'sort', 'direction']);
        $perPage = ListPagination::resolve($filters['per_page']);

        $query = ToilClaim::query()
            ->where('claim_type', 'cash')
            ->with(['employee:id,first_name,last_name,employee_code'])
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

        $claims = $query->paginate($perPage)->withQueryString();

        return Inertia::render('Admin/Overtime/ToilClaims/Index', [
            'claims' => $claims->through(fn (ToilClaim $claim) => [
                'id' => $claim->id,
                'employee' => $claim->employee?->fullName(),
                'employee_code' => $claim->employee?->employee_code,
                'hours' => (float) $claim->hours,
                'reason' => $claim->reason,
                'status' => $claim->status,
                'created_at' => $claim->created_at?->toDateString(),
            ]),
            'filters' => $filters,
        ]);
    }

    public function create(Request $request): Response
    {
        $this->authorize('create', ToilClaim::class);

        $accessibleBranchIds = $this->branchContext->accessibleBranchIds($request->user());

        return Inertia::render('Admin/Overtime/ToilClaims/Create', [
            'employees' => Employee::query()
                ->where('status', 'active')
                ->when($accessibleBranchIds !== null, fn ($q) => $q->whereIn('primary_branch_id', $accessibleBranchIds))
                ->orderBy('first_name')
                ->get(['id', 'first_name', 'last_name', 'employee_code']),
        ]);
    }

    public function store(StoreToilCashClaimRequest $request): RedirectResponse
    {
        $this->authorize('create', ToilClaim::class);

        $data = $request->validated();
        $employee = Employee::query()->findOrFail((int) $data['employee_id']);
        $leaveType = LeaveType::query()->where('code', 'TOIL')->firstOrFail();

        try {
            $this->toilClaims->requestCashClaim(
                employee: $employee,
                leaveType: $leaveType,
                hours: (float) $data['hours'],
                reason: $data['reason'] ?? null,
            );
        } catch (DomainException $e) {
            return back()->withErrors(['hours' => $e->getMessage()])->withInput();
        }

        return redirect()
            ->route('admin.overtime.toil-claims.index')
            ->with('success', __('TOIL Cash Claim Requested Successfully.'));
    }

    public function approve(Request $request, ToilClaim $toilClaim): RedirectResponse
    {
        $this->authorize('approve', $toilClaim);

        try {
            $this->toilClaims->approve($toilClaim, (int) $request->user()->id);
        } catch (DomainException|ValidationException $e) {
            return back()->with('error', $e->getMessage());
        }

        return back()->with('success', __('TOIL Cash Claim Approved Successfully.'));
    }

    public function reject(Request $request, ToilClaim $toilClaim): RedirectResponse
    {
        $this->authorize('reject', $toilClaim);

        try {
            $this->toilClaims->reject($toilClaim, (int) $request->user()->id);
        } catch (DomainException|ValidationException $e) {
            return back()->with('error', $e->getMessage());
        }

        return back()->with('success', __('TOIL Cash Claim Rejected Successfully.'));
    }

    public function cancel(Request $request, ToilClaim $toilClaim): RedirectResponse
    {
        $this->authorize('cancel', $toilClaim);

        try {
            $this->toilClaims->cancel($toilClaim, (int) $request->user()->id);
        } catch (DomainException|ValidationException $e) {
            return back()->with('error', $e->getMessage());
        }

        return back()->with('success', __('TOIL Cash Claim Cancelled Successfully.'));
    }
}
