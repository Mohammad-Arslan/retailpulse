<?php

declare(strict_types=1);

namespace App\Http\Controllers\Admin;

use App\DTOs\Loyalty\CreateLoyaltyProgramData;
use App\DTOs\Loyalty\UpdateLoyaltyProgramData;
use App\Enums\LoyaltyApprovalActionType;
use App\Enums\LoyaltyApprovalMode;
use App\Enums\LoyaltyApprovalThresholdType;
use App\Enums\LoyaltyCampaignStatus;
use App\Enums\LoyaltyCampaignType;
use App\Enums\LoyaltyExpiryType;
use App\Enums\LoyaltyProgramScopeType;
use App\Enums\LoyaltyProgramStatus;
use App\Enums\LoyaltyRuleType;
use App\Enums\LoyaltyScopeMode;
use App\Enums\LoyaltyTierQualificationType;
use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\Loyalty\StoreLoyaltyProgramRequest;
use App\Http\Requests\Admin\Loyalty\UpdateLoyaltyProgramRequest;
use App\Models\Branch;
use App\Models\Category;
use App\Models\LoyaltyProgram;
use App\Models\Role;
use App\Services\Loyalty\LoyaltyProgramService;
use App\Support\ListPagination;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

final class LoyaltyProgramController extends Controller
{
    public function __construct(
        private readonly LoyaltyProgramService $programs,
    ) {}

    public function index(Request $request): Response
    {
        $this->authorize('viewAny', LoyaltyProgram::class);

        $filters = ListPagination::filters($request, ['search', 'status', 'sort', 'direction']);

        $query = LoyaltyProgram::query()->withCount(['rules', 'wallets', 'tiers']);

        if (! empty($filters['search'])) {
            $query->where('name', 'like', '%'.$filters['search'].'%');
        }

        if (! empty($filters['status'])) {
            $query->where('status', $filters['status']);
        }

        $paginator = $query
            ->orderBy($filters['sort'] ?? 'name', $filters['direction'] ?? 'asc')
            ->paginate(ListPagination::resolve($filters['per_page']))
            ->withQueryString();

        return Inertia::render('Admin/Loyalty/Programs/Index', [
            'programs' => $paginator->through(fn (LoyaltyProgram $program) => [
                'id' => $program->id,
                'name' => $program->name,
                'status' => $program->status->value,
                'scope_type' => $program->scope_type->value,
                'rules_count' => $program->rules_count,
                'wallets_count' => $program->wallets_count,
                'tiers_count' => $program->tiers_count,
                'starts_at' => $program->starts_at?->toIso8601String(),
                'ends_at' => $program->ends_at?->toIso8601String(),
            ]),
            'filters' => $filters,
            'statuses' => LoyaltyProgramStatus::values(),
        ]);
    }

    public function create(): Response
    {
        $this->authorize('create', LoyaltyProgram::class);

        return Inertia::render('Admin/Loyalty/Programs/Create', [
            'branches' => Branch::query()->where('is_active', true)->orderBy('name')->get(['id', 'name']),
            'scopeTypes' => LoyaltyProgramScopeType::values(),
            'scopeModes' => LoyaltyScopeMode::values(),
        ]);
    }

    public function store(StoreLoyaltyProgramRequest $request): RedirectResponse
    {
        $this->authorize('create', LoyaltyProgram::class);

        $program = $this->programs->create(
            CreateLoyaltyProgramData::fromRequest($request),
            (int) $request->user()->id,
        );

        return redirect()
            ->route('admin.loyalty.programs.show', $program)
            ->with('success', __('Loyalty program created successfully.'));
    }

    public function show(Request $request, LoyaltyProgram $program): Response
    {
        $this->authorize('view', $program);

        $program->load(['rules', 'tiers', 'approvalPolicies.approverRole', 'expiryRules', 'campaigns', 'branches']);

        return Inertia::render('Admin/Loyalty/Programs/Show', [
            'tab' => (string) $request->query('tab', 'overview'),
            'program' => [
                'id' => $program->id,
                'name' => $program->name,
                'description' => $program->description,
                'status' => $program->status->value,
                'scope_type' => $program->scope_type->value,
                'earn_scope' => $program->earn_scope->value,
                'redeem_scope' => $program->redeem_scope->value,
                'allow_cross_branch_earn' => $program->allow_cross_branch_earn,
                'allow_cross_branch_redeem' => $program->allow_cross_branch_redeem,
                'starts_at' => $program->starts_at?->toIso8601String(),
                'ends_at' => $program->ends_at?->toIso8601String(),
                'branches' => $program->branches->map(fn ($b) => ['id' => $b->id, 'name' => $b->name]),
            ],
            'rules' => $program->rules->map(fn ($rule) => [
                'id' => $rule->id,
                'name' => $rule->name,
                'description' => $rule->description,
                'rule_type' => $rule->rule_type->value,
                'priority' => $rule->priority,
                'conditions_json' => $rule->conditions_json ?? [],
                'reward_json' => $rule->reward_json ?? [],
                'is_active' => $rule->is_active,
                'effective_from' => $rule->effective_from?->format('Y-m-d\TH:i'),
                'effective_to' => $rule->effective_to?->format('Y-m-d\TH:i'),
            ])->values(),
            'tiers' => $program->tiers->map(fn ($tier) => [
                'id' => $tier->id,
                'name' => $tier->name,
                'tier_level' => $tier->tier_level,
                'qualification_type' => $tier->qualification_type->value,
                'qualification_value' => (float) $tier->qualification_value,
                'multiplier' => (float) $tier->multiplier,
                'benefits_json' => $tier->benefits_json ?? [],
                'status' => $tier->status,
            ])->values(),
            'approvalPolicies' => $program->approvalPolicies->map(fn ($p) => [
                'id' => $p->id,
                'action_type' => $p->action_type->value,
                'threshold_type' => $p->threshold_type->value,
                'threshold_value' => (float) $p->threshold_value,
                'approval_mode' => $p->approval_mode->value,
                'approver_role_id' => $p->approver_role_id,
                'approver_role' => $p->approverRole?->name,
                'is_active' => $p->is_active,
            ])->values(),
            'expiryRules' => $program->expiryRules->map(fn ($r) => [
                'id' => $r->id,
                'expiry_type' => $r->expiry_type->value,
                'value' => $r->value,
                'grace_period_days' => $r->grace_period_days,
            ])->values(),
            'campaigns' => $program->campaigns->map(fn ($c) => [
                'id' => $c->id,
                'name' => $c->name,
                'description' => $c->description,
                'campaign_type' => $c->campaign_type->value,
                'configuration_json' => $c->configuration_json ?? [],
                'status' => $c->status->value,
                'starts_at' => $c->starts_at?->format('Y-m-d\TH:i'),
                'ends_at' => $c->ends_at?->format('Y-m-d\TH:i'),
            ])->values(),
            'options' => [
                'ruleTypes' => LoyaltyRuleType::values(),
                'tierQualificationTypes' => LoyaltyTierQualificationType::values(),
                'approvalActionTypes' => LoyaltyApprovalActionType::values(),
                'approvalThresholdTypes' => LoyaltyApprovalThresholdType::values(),
                'approvalModes' => LoyaltyApprovalMode::values(),
                'expiryTypes' => LoyaltyExpiryType::values(),
                'campaignTypes' => LoyaltyCampaignType::values(),
                'campaignStatuses' => LoyaltyCampaignStatus::values(),
            ],
            'branches' => Branch::query()->where('is_active', true)->orderBy('name')->get(['id', 'name']),
            'categories' => Category::query()->where('is_active', true)->orderBy('name')->get(['id', 'name']),
            'roles' => Role::query()->orderBy('name')->get(['id', 'name']),
        ]);
    }

    public function edit(LoyaltyProgram $program): Response
    {
        $this->authorize('update', $program);

        $program->load('branches');

        return Inertia::render('Admin/Loyalty/Programs/Edit', [
            'program' => [
                'id' => $program->id,
                'name' => $program->name,
                'description' => $program->description,
                'status' => $program->status->value,
                'scope_type' => $program->scope_type->value,
                'earn_scope' => $program->earn_scope->value,
                'redeem_scope' => $program->redeem_scope->value,
                'allow_cross_branch_earn' => $program->allow_cross_branch_earn,
                'allow_cross_branch_redeem' => $program->allow_cross_branch_redeem,
                'starts_at' => $program->starts_at?->format('Y-m-d\TH:i'),
                'ends_at' => $program->ends_at?->format('Y-m-d\TH:i'),
                'branch_ids' => $program->branches->pluck('id'),
            ],
            'branches' => Branch::query()->where('is_active', true)->orderBy('name')->get(['id', 'name']),
            'scopeTypes' => LoyaltyProgramScopeType::values(),
            'scopeModes' => LoyaltyScopeMode::values(),
        ]);
    }

    public function update(UpdateLoyaltyProgramRequest $request, LoyaltyProgram $program): RedirectResponse
    {
        $this->authorize('update', $program);

        $this->programs->update(
            $program,
            UpdateLoyaltyProgramData::fromRequest($request),
            (int) $request->user()->id,
        );

        return redirect()
            ->route('admin.loyalty.programs.show', $program)
            ->with('success', __('Loyalty program updated successfully.'));
    }

    public function activate(LoyaltyProgram $program, Request $request): RedirectResponse
    {
        $this->authorize('update', $program);

        $this->programs->activate($program, (int) $request->user()->id);

        return back()->with('success', __('Loyalty program activated.'));
    }

    public function deactivate(LoyaltyProgram $program, Request $request): RedirectResponse
    {
        $this->authorize('update', $program);

        $this->programs->deactivate($program, (int) $request->user()->id);

        return back()->with('success', __('Loyalty program deactivated.'));
    }
}
