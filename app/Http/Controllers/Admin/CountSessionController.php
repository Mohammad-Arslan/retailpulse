<?php

declare(strict_types=1);

namespace App\Http\Controllers\Admin;

use App\DTOs\CountSession\CreateCountSessionData;
use App\DTOs\CountSession\SubmitCountLinesData;
use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\StoreCountSessionRequest;
use App\Http\Requests\Admin\SubmitCountLinesRequest;
use App\Models\CountSession;
use App\Models\Warehouse;
use App\Repositories\Contracts\BranchRepositoryInterface;
use App\Repositories\Contracts\CountSessionRepositoryInterface;
use App\Services\BranchContextService;
use App\Services\CountSessionService;
use App\Support\BranchContext;
use App\Support\CountScopeOptions;
use App\Support\ListPagination;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

final class CountSessionController extends Controller
{
    public function __construct(
        private readonly CountSessionRepositoryInterface $sessions,
        private readonly CountSessionService $countSessionService,
        private readonly BranchRepositoryInterface $branches,
        private readonly BranchContextService $branchContext,
    ) {}

    public function index(Request $request): Response
    {
        $this->authorize('viewAny', CountSession::class);

        $filters = ListPagination::filters($request, ['warehouse_id', 'status']);
        $branchId = app(BranchContext::class)->branchId;

        if ($branchId !== null) {
            $filters['branch_id'] = $branchId;
        }

        $paginator = $this->sessions
            ->paginate($filters, ListPagination::resolve($filters['per_page'] ?? null))
            ->through(fn (CountSession $session) => [
                'id' => $session->id,
                'reference_no' => $session->reference_no,
                'warehouse' => $session->warehouse?->only('id', 'name', 'code'),
                'status' => $session->status->value,
                'scope_type' => $session->scope_type->value,
                'blind_count' => $session->blind_count,
                'created_at' => $session->created_at?->toIso8601String(),
            ]);

        return Inertia::render('Admin/CountSessions/Index', [
            'sessions' => $paginator,
            'filters' => $filters,
            'warehouses' => $this->warehouseOptions($request),
        ]);
    }

    public function create(Request $request): Response
    {
        $this->authorize('create', CountSession::class);

        $accessibleIds = $this->branchContext->accessibleBranchIds($request->user());
        $branchId = app(BranchContext::class)->branchId;

        $scopeOptions = CountScopeOptions::forRequest($request, $this->branchContext);

        return Inertia::render('Admin/CountSessions/Create', [
            'branches' => $this->branches->allActive($accessibleIds)
                ->map(fn ($b) => ['id' => $b->id, 'name' => $b->name, 'code' => $b->code])
                ->values()
                ->all(),
            'warehouses' => $this->warehouseOptions($request),
            'defaultBranchId' => $branchId,
            ...$scopeOptions,
        ]);
    }

    public function store(StoreCountSessionRequest $request): RedirectResponse
    {
        $this->authorize('create', CountSession::class);

        $session = $this->countSessionService->create(CreateCountSessionData::fromRequest($request));

        return redirect()
            ->route('admin.count-sessions.show', $session)
            ->with('success', __('Count session created successfully.'));
    }

    public function show(CountSession $countSession): Response
    {
        $this->authorize('view', $countSession);

        $session = $this->sessions->findByIdWithRelations($countSession->id) ?? $countSession;
        $hideSystemQty = $session->blind_count && $session->status->isEditable();

        return Inertia::render('Admin/CountSessions/Show', [
            'session' => [
                'id' => $session->id,
                'reference_no' => $session->reference_no,
                'status' => $session->status->value,
                'scope_type' => $session->scope_type->value,
                'blind_count' => $session->blind_count,
                'freeze_mode' => $session->freeze_mode,
                'warehouse' => $session->warehouse?->only('id', 'name', 'code'),
                'lines' => $session->lines->map(fn ($line) => [
                    'id' => $line->id,
                    'variant' => [
                        'id' => $line->variant?->id,
                        'sku' => $line->variant?->sku,
                        'name' => $line->variant?->displayName(),
                    ],
                    'bin_code' => $line->binLocation?->bin_code,
                    'batch_no' => $line->batch_no,
                    'system_qty' => $hideSystemQty ? null : $line->system_qty,
                    'counted_qty' => $line->counted_qty,
                    'variance_qty' => $line->variance_qty,
                    'variance_value' => $line->variance_value,
                ])->values()->all(),
            ],
        ]);
    }

    public function start(CountSession $countSession): RedirectResponse
    {
        $this->authorize('update', $countSession);

        $this->countSessionService->start($countSession);

        return redirect()
            ->route('admin.count-sessions.show', $countSession)
            ->with('success', __('Count session started. Count sheets generated.'));
    }

    public function submitCounts(SubmitCountLinesRequest $request, CountSession $countSession): RedirectResponse
    {
        $this->authorize('update', $countSession);

        $this->countSessionService->submitCounts(
            $countSession,
            new SubmitCountLinesData(
                lines: $request->validated('lines'),
                userId: (int) $request->user()->id,
            ),
        );

        return redirect()
            ->route('admin.count-sessions.show', $countSession)
            ->with('success', __('Counts submitted for review.'));
    }

    public function approve(CountSession $countSession, Request $request): RedirectResponse
    {
        $this->authorize('approve', $countSession);

        $this->countSessionService->approve($countSession, (int) $request->user()->id);

        return redirect()
            ->route('admin.count-sessions.show', $countSession)
            ->with('success', __('Count session approved.'));
    }

    public function post(CountSession $countSession, Request $request): RedirectResponse
    {
        $this->authorize('approve', $countSession);

        $this->countSessionService->post($countSession, (int) $request->user()->id);

        return redirect()
            ->route('admin.count-sessions.show', $countSession)
            ->with('success', __('Count variances posted to inventory.'));
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
