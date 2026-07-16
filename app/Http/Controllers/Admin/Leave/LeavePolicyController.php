<?php

declare(strict_types=1);

namespace App\Http\Controllers\Admin\Leave;

use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\Leave\UpdateLeavePolicyRequest;
use App\Models\LeavePolicy;
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
                'leave_type' => $policy->leaveType?->name,
                'leave_type_code' => $policy->leaveType?->code,
                'legal_entity' => $policy->legalEntity?->legal_name,
                'accrual_method' => $policy->accrual_method,
                'exclude_public_holidays' => $policy->exclude_public_holidays,
                'effective_from' => $policy->effective_from?->toDateString(),
                'effective_to' => $policy->effective_to?->toDateString(),
                'status' => $policy->status,
            ]);

        return Inertia::render('Admin/Leave/Policies/Index', [
            'policies' => $policies,
            'filters' => $filters,
        ]);
    }

    public function update(UpdateLeavePolicyRequest $request, LeavePolicy $policy): RedirectResponse
    {
        $this->authorize('update', $policy);

        $policy->update($request->validated());

        return back()->with('success', __('Leave Policy Updated Successfully.'));
    }
}
