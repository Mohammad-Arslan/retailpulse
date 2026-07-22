<?php

declare(strict_types=1);

namespace App\Http\Controllers\Admin\Overtime;

use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\Overtime\StoreOvertimePolicyRequest;
use App\Http\Requests\Admin\Overtime\UpdateOvertimePolicyRequest;
use App\Models\Branch;
use App\Models\OrganizationEntity;
use App\Models\OvertimePolicy;
use App\Services\BranchContextService;
use App\Support\ListPagination;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Inertia\Inertia;
use Inertia\Response;

final class OvertimePolicyController extends Controller
{
    public function __construct(
        private readonly BranchContextService $branchContext,
    ) {}

    public function index(Request $request): Response
    {
        $this->authorize('viewAny', OvertimePolicy::class);

        $filters = ListPagination::filters($request, ['search', 'status', 'sort', 'direction']);
        $perPage = ListPagination::resolve($filters['per_page']);
        $accessibleBranchIds = $this->branchContext->accessibleBranchIds($request->user());

        $query = OvertimePolicy::query()
            ->with([
                'legalEntity:id,legal_name',
                'branch:id,name,code',
                'multipliers:id,overtime_policy_id,day_type,multiplier,compensation_type',
            ])
            ->when($filters['status'] ?? null, fn ($q, string $status) => $q->where('status', $status))
            ->when($accessibleBranchIds !== null, fn ($q) => $q->where(function ($inner) use ($accessibleBranchIds): void {
                $inner->whereNull('branch_id')->orWhereIn('branch_id', $accessibleBranchIds);
            }))
            ->orderBy($filters['sort'] ?? 'priority', $filters['direction'] ?? 'asc');

        $policies = $query->paginate($perPage)->withQueryString();

        return Inertia::render('Admin/Overtime/Policies/Index', [
            'policies' => $policies->through(fn (OvertimePolicy $policy) => [
                'id' => $policy->id,
                'legal_entity_id' => $policy->legal_entity_id,
                'legal_entity' => $policy->legalEntity?->legal_name,
                'branch_id' => $policy->branch_id,
                'branch' => $policy->branch?->name,
                'branch_code' => $policy->branch?->code,
                'daily_threshold_minutes' => $policy->daily_threshold_minutes,
                'weekly_threshold_minutes' => $policy->weekly_threshold_minutes,
                'rest_day_applies' => $policy->rest_day_applies,
                'public_holiday_applies' => $policy->public_holiday_applies,
                'toil_expiry_months' => $policy->toil_expiry_months,
                'effective_from' => $policy->effective_from?->toDateString(),
                'effective_to' => $policy->effective_to?->toDateString(),
                'priority' => $policy->priority,
                'status' => $policy->status,
                'multipliers' => $policy->multipliers->map(fn ($multiplier) => [
                    'id' => $multiplier->id,
                    'day_type' => $multiplier->day_type,
                    'multiplier' => (string) $multiplier->multiplier,
                    'compensation_type' => $multiplier->compensation_type,
                ])->values()->all(),
            ]),
            'filters' => $filters,
            'legalEntities' => OrganizationEntity::query()
                ->where('status', 'active')
                ->orderBy('legal_name')
                ->get(['id', 'legal_name']),
            'branches' => Branch::query()
                ->where('is_active', true)
                ->when($accessibleBranchIds !== null, fn ($q) => $q->whereIn('id', $accessibleBranchIds))
                ->orderBy('name')
                ->get(['id', 'name', 'code']),
        ]);
    }

    public function store(StoreOvertimePolicyRequest $request): RedirectResponse
    {
        $this->authorize('create', OvertimePolicy::class);

        $validated = $request->validated();
        $multipliers = $validated['multipliers'];
        unset($validated['multipliers']);

        DB::transaction(function () use ($validated, $multipliers): void {
            $policy = OvertimePolicy::query()->create($validated);
            $this->syncMultipliers($policy, $multipliers);
        });

        return back()->with('success', __('Overtime Policy Created Successfully.'));
    }

    public function update(UpdateOvertimePolicyRequest $request, OvertimePolicy $overtimePolicy): RedirectResponse
    {
        $this->authorize('update', $overtimePolicy);

        $validated = $request->validated();
        $multipliers = $validated['multipliers'];
        unset($validated['multipliers']);

        DB::transaction(function () use ($overtimePolicy, $validated, $multipliers): void {
            $overtimePolicy->update($validated);
            $this->syncMultipliers($overtimePolicy, $multipliers);
        });

        return back()->with('success', __('Overtime Policy Updated Successfully.'));
    }

    /**
     * @param  list<array{day_type: string, multiplier: mixed, compensation_type: string}>  $multipliers
     */
    private function syncMultipliers(OvertimePolicy $policy, array $multipliers): void
    {
        $policy->multipliers()->delete();

        foreach ($multipliers as $row) {
            $policy->multipliers()->create([
                'day_type' => $row['day_type'],
                'multiplier' => $row['multiplier'],
                'compensation_type' => $row['compensation_type'],
            ]);
        }
    }
}
