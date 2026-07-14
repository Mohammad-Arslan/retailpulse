<?php

declare(strict_types=1);

namespace App\Http\Controllers\Admin\Overtime;

use App\Http\Controllers\Controller;
use App\Models\OvertimePolicy;
use App\Support\ListPagination;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

final class OvertimePolicyController extends Controller
{
    public function index(Request $request): Response
    {
        $this->authorize('viewAny', OvertimePolicy::class);

        $filters = ListPagination::filters($request, ['search', 'status', 'sort', 'direction']);
        $perPage = ListPagination::resolve($filters['per_page']);

        $query = OvertimePolicy::query()
            ->with([
                'legalEntity:id,legal_name',
                'branch:id,name,code',
                'multipliers:id,overtime_policy_id,day_type,multiplier',
            ])
            ->when($filters['status'] ?? null, fn ($q, string $status) => $q->where('status', $status))
            ->orderBy($filters['sort'] ?? 'priority', $filters['direction'] ?? 'asc');

        $policies = $query->paginate($perPage)->withQueryString();

        return Inertia::render('Admin/Overtime/Policies/Index', [
            'policies' => $policies->through(fn (OvertimePolicy $policy) => [
                'id' => $policy->id,
                'legal_entity' => $policy->legalEntity?->legal_name,
                'branch' => $policy->branch?->name,
                'branch_code' => $policy->branch?->code,
                'daily_threshold_minutes' => $policy->daily_threshold_minutes,
                'weekly_threshold_minutes' => $policy->weekly_threshold_minutes,
                'rest_day_applies' => $policy->rest_day_applies,
                'public_holiday_applies' => $policy->public_holiday_applies,
                'effective_from' => $policy->effective_from?->toDateString(),
                'effective_to' => $policy->effective_to?->toDateString(),
                'priority' => $policy->priority,
                'status' => $policy->status,
                'multipliers' => $policy->multipliers->map(fn ($multiplier) => [
                    'id' => $multiplier->id,
                    'day_type' => $multiplier->day_type,
                    'multiplier' => (string) $multiplier->multiplier,
                ])->values()->all(),
            ]),
            'filters' => $filters,
        ]);
    }
}
