<?php

declare(strict_types=1);

namespace App\Http\Controllers\Admin;

use App\DTOs\CountSchedule\CreateCountScheduleRuleData;
use App\DTOs\CountSchedule\UpdateCountScheduleRuleData;
use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\StoreCountScheduleRuleRequest;
use App\Http\Requests\Admin\UpdateCountScheduleRuleRequest;
use App\Models\CountScheduleRule;
use App\Models\Warehouse;
use App\Repositories\Contracts\BranchRepositoryInterface;
use App\Services\BranchContextService;
use App\Services\CountScheduleRuleService;
use App\Support\BranchContext;
use App\Support\ListPagination;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

final class CountScheduleRuleController extends Controller
{
    public function __construct(
        private readonly CountScheduleRuleService $scheduleRules,
        private readonly BranchRepositoryInterface $branches,
        private readonly BranchContextService $branchContext,
    ) {}

    public function index(Request $request): Response
    {
        $this->authorize('viewAny', CountScheduleRule::class);

        $branchId = app(BranchContext::class)->branchId;
        $accessibleIds = $this->branchContext->accessibleBranchIds($request->user());

        $rules = CountScheduleRule::query()
            ->with(['warehouse', 'branch'])
            ->when($branchId !== null, fn ($q) => $q->where('branch_id', $branchId))
            ->when(
                $accessibleIds !== null,
                fn ($q) => $q->whereIn('branch_id', $accessibleIds),
            )
            ->orderByDesc('created_at')
            ->paginate(ListPagination::resolve($request->integer('per_page', 20)))
            ->withQueryString()
            ->through(fn (CountScheduleRule $rule) => [
                'id' => $rule->id,
                'warehouse' => $rule->warehouse?->only('id', 'name', 'code'),
                'branch' => $rule->branch?->only('id', 'name'),
                'scope_type' => $rule->scope_type->value,
                'scope_id' => $rule->scope_id,
                'frequency' => $rule->frequency->value,
                'day_of_week' => $rule->day_of_week,
                'day_of_month' => $rule->day_of_month,
                'blind_count' => $rule->blind_count,
                'is_active' => $rule->is_active,
                'last_run_at' => $rule->last_run_at?->toIso8601String(),
            ]);

        return Inertia::render('Admin/CountScheduleRules/Index', [
            'rules' => $rules,
        ]);
    }

    public function create(Request $request): Response
    {
        $this->authorize('create', CountScheduleRule::class);

        $accessibleIds = $this->branchContext->accessibleBranchIds($request->user());
        $branchId = app(BranchContext::class)->branchId;

        return Inertia::render('Admin/CountScheduleRules/Create', [
            'branches' => $this->branches->allActive($accessibleIds)
                ->map(fn ($b) => ['id' => $b->id, 'name' => $b->name, 'code' => $b->code])
                ->values()
                ->all(),
            'warehouses' => $this->warehouseOptions($request),
            'defaultBranchId' => $branchId,
        ]);
    }

    public function store(StoreCountScheduleRuleRequest $request): RedirectResponse
    {
        $this->authorize('create', CountScheduleRule::class);

        $rule = $this->scheduleRules->create(CreateCountScheduleRuleData::fromRequest($request));

        return redirect()
            ->route('admin.count-schedule-rules.edit', $rule)
            ->with('success', __('Count schedule created successfully.'));
    }

    public function edit(CountScheduleRule $countScheduleRule): Response
    {
        $this->authorize('update', $countScheduleRule);

        $countScheduleRule->load(['warehouse', 'branch']);

        return Inertia::render('Admin/CountScheduleRules/Edit', [
            'rule' => [
                'id' => $countScheduleRule->id,
                'branch_id' => $countScheduleRule->branch_id,
                'branch_name' => $countScheduleRule->branch?->name ?? '',
                'warehouse_id' => $countScheduleRule->warehouse_id,
                'warehouse_name' => $countScheduleRule->warehouse?->name ?? '',
                'scope_type' => $countScheduleRule->scope_type->value,
                'scope_id' => $countScheduleRule->scope_id,
                'frequency' => $countScheduleRule->frequency->value,
                'day_of_week' => $countScheduleRule->day_of_week,
                'day_of_month' => $countScheduleRule->day_of_month,
                'blind_count' => $countScheduleRule->blind_count,
                'is_active' => $countScheduleRule->is_active,
                'last_run_at' => $countScheduleRule->last_run_at?->toIso8601String(),
            ],
        ]);
    }

    public function update(
        UpdateCountScheduleRuleRequest $request,
        CountScheduleRule $countScheduleRule,
    ): RedirectResponse {
        $this->authorize('update', $countScheduleRule);

        $this->scheduleRules->update(
            $countScheduleRule,
            UpdateCountScheduleRuleData::fromRequest($request),
        );

        return redirect()
            ->route('admin.count-schedule-rules.edit', $countScheduleRule)
            ->with('success', __('Count schedule updated successfully.'));
    }

    public function destroy(CountScheduleRule $countScheduleRule): RedirectResponse
    {
        $this->authorize('delete', $countScheduleRule);

        $this->scheduleRules->deactivate($countScheduleRule);

        return redirect()
            ->route('admin.count-schedule-rules.index')
            ->with('success', __('Count schedule deactivated.'));
    }

    /**
     * @return list<array{id: int, name: string, code: string}>
     */
    private function warehouseOptions(Request $request): array
    {
        $branchId = app(BranchContext::class)->branchId;
        $accessibleIds = $this->branchContext->accessibleBranchIds($request->user());

        return Warehouse::query()
            ->where('is_active', true)
            ->when($branchId !== null, fn ($q) => $q->where('branch_id', $branchId))
            ->when($accessibleIds !== null, fn ($q) => $q->whereIn('branch_id', $accessibleIds))
            ->orderBy('name')
            ->get(['id', 'name', 'code'])
            ->map(fn (Warehouse $w) => $w->only('id', 'name', 'code'))
            ->values()
            ->all();
    }
}
