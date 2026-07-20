<?php

declare(strict_types=1);

namespace App\Http\Controllers\Admin\Leave;

use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\Leave\StoreLeaveEntitlementRequest;
use App\Http\Requests\Admin\Leave\UpdateLeaveEntitlementRequest;
use App\Models\Employee;
use App\Models\LeaveEntitlement;
use App\Models\LeaveType;
use App\Support\ListPagination;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;
use Inertia\Inertia;
use Inertia\Response;

final class LeaveEntitlementController extends Controller
{
    public function index(Request $request): Response
    {
        $this->authorize('viewAny', LeaveEntitlement::class);

        $filters = ListPagination::filters($request, ['search', 'leave_type_id', 'sort', 'direction']);
        $perPage = ListPagination::resolve($filters['per_page']);

        $query = LeaveEntitlement::query()
            ->with(['employee:id,first_name,last_name,employee_code', 'leaveType:id,code,name'])
            ->when($filters['search'] ?? null, function ($q, string $search): void {
                $q->whereHas('employee', function ($employee) use ($search): void {
                    $employee->where('first_name', 'like', "%{$search}%")
                        ->orWhere('last_name', 'like', "%{$search}%")
                        ->orWhere('employee_code', 'like', "%{$search}%");
                });
            })
            ->when($filters['leave_type_id'] ?? null, fn ($q, $id) => $q->where('leave_type_id', (int) $id))
            ->orderBy($filters['sort'] ?? 'updated_at', $filters['direction'] ?? 'desc');

        $entitlements = $query->paginate($perPage)->withQueryString();

        return Inertia::render('Admin/Leave/Entitlements/Index', [
            'entitlements' => $entitlements->through(fn (LeaveEntitlement $entitlement) => [
                'id' => $entitlement->id,
                'employee' => $entitlement->employee?->fullName(),
                'employee_code' => $entitlement->employee?->employee_code,
                'leave_type' => $entitlement->leaveType?->name,
                'leave_type_code' => $entitlement->leaveType?->code,
                'accrued_days' => (float) $entitlement->accrued_days,
                'used_days' => (float) $entitlement->used_days,
                'encashed_days' => (float) $entitlement->encashed_days,
                'carried_forward_days' => (float) $entitlement->carried_forward_days,
                'remaining_days' => (float) $entitlement->remaining_days,
                'accrual_last_run_on' => $entitlement->accrual_last_run_on?->toDateString(),
                'granted_manually' => $entitlement->granted_manually,
            ]),
            'filters' => $filters,
            'leaveTypes' => LeaveType::query()
                ->where('status', 'active')
                ->orderBy('name')
                ->get(['id', 'code', 'name']),
            'employees' => Employee::query()
                ->where('status', 'active')
                ->orderBy('first_name')
                ->get(['id', 'first_name', 'last_name', 'employee_code']),
        ]);
    }

    public function store(StoreLeaveEntitlementRequest $request): RedirectResponse
    {
        $this->authorize('create', LeaveEntitlement::class);

        $data = $request->validated();
        $employee = Employee::query()->findOrFail((int) $data['employee_id']);
        $leaveType = LeaveType::query()->findOrFail((int) $data['leave_type_id']);

        $existing = LeaveEntitlement::query()
            ->where('employee_id', $employee->id)
            ->where('leave_type_id', $leaveType->id)
            ->whereNull('fiscal_year_id')
            ->exists();

        if ($existing) {
            throw ValidationException::withMessages([
                'leave_type_id' => __('This employee already has an entitlement for this leave type.'),
            ]);
        }

        LeaveEntitlement::query()->create([
            'employee_id' => $employee->id,
            'leave_type_id' => $leaveType->id,
            'fiscal_year_id' => null,
            'accrued_days' => $data['accrued_days'],
            'used_days' => 0,
            'encashed_days' => 0,
            'carried_forward_days' => $data['carried_forward_days'] ?? 0,
            'accrual_last_run_on' => now()->toDateString(),
            'granted_manually' => true,
        ]);

        return back()->with('success', __('Leave Entitlement Granted Successfully.'));
    }

    public function update(UpdateLeaveEntitlementRequest $request, LeaveEntitlement $entitlement): RedirectResponse
    {
        $this->authorize('update', $entitlement);

        $entitlement->update($request->validated());

        return back()->with('success', __('Leave Entitlement Updated Successfully.'));
    }
}
