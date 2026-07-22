<?php

declare(strict_types=1);

namespace App\Http\Controllers\Admin\Leave;

use App\Http\Controllers\Controller;
use App\Models\LeaveYearEndRun;
use App\Services\BranchContextService;
use App\Support\BranchScope;
use App\Support\ListPagination;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

final class LeaveYearEndRunController extends Controller
{
    public function __construct(
        private readonly BranchContextService $branchContext,
    ) {}

    public function index(Request $request): Response
    {
        $this->authorize('viewAny', LeaveYearEndRun::class);

        $filters = ListPagination::filters($request, ['sort', 'direction']);
        $perPage = ListPagination::resolve($filters['per_page']);

        $query = LeaveYearEndRun::query()
            ->with(['legalEntity:id,legal_name', 'employee:id,first_name,last_name,employee_code'])
            ->orderBy($filters['sort'] ?? 'executed_at', $filters['direction'] ?? 'desc');

        BranchScope::applyViaEmployee($query, $this->branchContext->accessibleBranchIds($request->user()));

        $runs = $query->paginate($perPage)->withQueryString();

        return Inertia::render('Admin/Leave/YearEndRuns/Index', [
            'runs' => $runs->through(fn (LeaveYearEndRun $run) => [
                'id' => $run->id,
                'legal_entity' => $run->legalEntity?->legal_name,
                'employee' => $run->employee?->fullName(),
                'period_label' => $run->period_label,
                'status' => $run->status,
                'carried_forward' => (float) ($run->totals_json['carried_forward'] ?? 0),
                'expired' => (float) ($run->totals_json['expired'] ?? 0),
                'encashed' => (float) ($run->totals_json['encashed'] ?? 0),
                'entitlements_processed' => (int) ($run->totals_json['entitlements_processed'] ?? 0),
                'executed_at' => $run->executed_at?->toDateTimeString(),
            ]),
            'filters' => $filters,
        ]);
    }
}
