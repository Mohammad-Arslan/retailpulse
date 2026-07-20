<?php

declare(strict_types=1);

namespace App\Http\Controllers\Admin\Leave;

use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\Leave\StoreLeavePolicyRequest;
use App\Http\Requests\Admin\Leave\UpdateLeavePolicyRequest;
use App\Jobs\Leave\ReevaluateLeaveEligibilityForPolicyJob;
use App\Models\Grade;
use App\Models\HrEmploymentType;
use App\Models\LeavePolicy;
use App\Models\LeaveType;
use App\Models\OrganizationEntity;
use App\Support\ListPagination;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

final class LeavePolicyController extends Controller
{
    public function index(Request $request): Response
    {
        $this->authorize('viewAny', LeavePolicy::class);

        $filters = ListPagination::filters($request, ['status', 'sort', 'direction']);
        $perPage = ListPagination::resolve($filters['per_page']);

        $policies = LeavePolicy::query()
            ->with(['leaveType:id,code,name', 'legalEntity:id,legal_name'])
            ->when($filters['status'] ?? null, fn ($q, string $status) => $q->where('status', $status))
            ->orderBy($filters['sort'] ?? 'effective_from', $filters['direction'] ?? 'desc')
            ->paginate($perPage)
            ->withQueryString()
            ->through(fn (LeavePolicy $policy) => [
                'id' => $policy->id,
                'leave_type_id' => $policy->leave_type_id,
                'leave_type' => $policy->leaveType?->name,
                'leave_type_code' => $policy->leaveType?->code,
                'legal_entity_id' => $policy->legal_entity_id,
                'legal_entity' => $policy->legalEntity?->legal_name,
                'accrual_method' => $policy->accrual_method,
                'accrual_rate' => (string) $policy->accrual_rate,
                'max_balance' => $policy->max_balance !== null ? (string) $policy->max_balance : null,
                'carry_forward_limit' => $policy->carry_forward_limit !== null ? (string) $policy->carry_forward_limit : null,
                'carry_forward_expiry_months' => $policy->carry_forward_expiry_months,
                'negative_leave_balance_policy' => $policy->negative_leave_balance_policy?->value,
                'eligibility_json' => $policy->eligibility_json,
                'proration_on_join' => $policy->proration_on_join,
                'exclude_public_holidays' => $policy->exclude_public_holidays,
                'exclude_weekends' => $policy->exclude_weekends,
                'short_leave_max_hours' => $policy->short_leave_max_hours !== null ? (string) $policy->short_leave_max_hours : null,
                'short_leave_max_requests_per_month' => $policy->short_leave_max_requests_per_month,
                'out_station_deducts_balance' => $policy->out_station_deducts_balance,
                'encashment_allowed' => $policy->encashment_allowed,
                'encashment_max_days' => $policy->encashment_max_days !== null ? (string) $policy->encashment_max_days : null,
                'encashment_requires_approval' => $policy->encashment_requires_approval,
                'year_end_excess_disposition' => $policy->year_end_excess_disposition,
                'effective_from' => $policy->effective_from?->toDateString(),
                'effective_to' => $policy->effective_to?->toDateString(),
                'status' => $policy->status,
            ]);

        return Inertia::render('Admin/Leave/Policies/Index', [
            'policies' => $policies,
            'filters' => $filters,
            'leaveTypes' => LeaveType::query()
                ->where('status', 'active')
                ->orderBy('name')
                ->get(['id', 'code', 'name']),
            'legalEntities' => OrganizationEntity::query()
                ->where('status', 'active')
                ->orderBy('legal_name')
                ->get(['id', 'legal_name']),
            'grades' => Grade::query()
                ->where('status', 'active')
                ->orderBy('name')
                ->get(['id', 'name']),
            'employmentTypes' => HrEmploymentType::query()
                ->where('status', 'active')
                ->orderBy('name')
                ->get(['code', 'name']),
        ]);
    }

    public function store(StoreLeavePolicyRequest $request): RedirectResponse
    {
        $this->authorize('create', LeavePolicy::class);

        $policy = LeavePolicy::query()->create($request->validated());

        // Always re-check on create: a brand-new policy may make employees
        // who already exist newly eligible for a leave type they had no
        // policy for at all before.
        ReevaluateLeaveEligibilityForPolicyJob::dispatch($policy);

        return back()->with('success', __('Leave Policy Created Successfully.'));
    }

    public function update(UpdateLeavePolicyRequest $request, LeavePolicy $policy): RedirectResponse
    {
        $this->authorize('update', $policy);

        // Carbon casts don't compare reliably with ===, so snapshot as plain
        // scalars rather than diffing the raw ->only() arrays.
        $snapshot = fn (LeavePolicy $p): array => [
            'eligibility_json' => $p->eligibility_json,
            'effective_from' => $p->effective_from?->toDateString(),
            'effective_to' => $p->effective_to?->toDateString(),
        ];

        $before = $snapshot($policy);

        $policy->update($request->validated());

        $after = $snapshot($policy);

        if ($before !== $after) {
            ReevaluateLeaveEligibilityForPolicyJob::dispatch($policy);
        }

        return back()->with('success', __('Leave Policy Updated Successfully.'));
    }
}
