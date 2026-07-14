<?php

declare(strict_types=1);

namespace App\Http\Controllers\Admin\Payroll;

use App\Http\Controllers\Controller;
use App\Models\Branch;
use App\Models\OrganizationEntity;
use App\Models\PayrollRun;
use App\Services\Payroll\PayrollRunService;
use App\Support\ListPagination;
use DomainException;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

final class PayrollRunController extends Controller
{
    public function __construct(
        private readonly PayrollRunService $payrollRuns,
    ) {}

    public function index(Request $request): Response
    {
        $this->authorize('viewAny', PayrollRun::class);

        $filters = ListPagination::filters($request, ['legal_entity_id', 'status', 'sort', 'direction']);
        $perPage = ListPagination::resolve($filters['per_page']);

        $query = PayrollRun::query()
            ->with(['legalEntity:id,legal_name', 'branch:id,name'])
            ->when($filters['legal_entity_id'] ?? null, fn ($q, $id) => $q->where('legal_entity_id', $id))
            ->when($filters['status'] ?? null, fn ($q, string $status) => $q->where('status', $status))
            ->orderBy($filters['sort'] ?? 'period_start', $filters['direction'] ?? 'desc');

        $runs = $query->paginate($perPage)->withQueryString();

        return Inertia::render('Admin/Payroll/Runs/Index', [
            'runs' => $runs->through(fn (PayrollRun $run) => [
                'id' => $run->id,
                'payroll_number' => $run->payroll_number,
                'legal_entity' => $run->legalEntity?->legal_name,
                'branch' => $run->branch?->name,
                'period_start' => $run->period_start?->toDateString(),
                'period_end' => $run->period_end?->toDateString(),
                'currency_code' => $run->currency_code,
                'status' => $run->status,
                'totals' => $run->totals_json,
                'journal_entry_id' => $run->journal_entry_id,
            ]),
            'entities' => OrganizationEntity::query()
                ->where('status', 'active')
                ->orderBy('legal_name')
                ->get(['id', 'legal_name'])
                ->map(fn ($e) => ['id' => $e->id, 'name' => $e->legal_name]),
            'branches' => Branch::query()->where('is_active', true)->orderBy('name')->get(['id', 'name']),
            'filters' => $filters,
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $this->authorize('create', PayrollRun::class);

        $data = $request->validate([
            'legal_entity_id' => ['required', 'integer', 'exists:organization_entities,id'],
            'branch_id' => ['nullable', 'integer', 'exists:branches,id'],
            'period_start' => ['required', 'date'],
            'period_end' => ['required', 'date', 'after_or_equal:period_start'],
            'currency_code' => ['required', 'string', 'size:3'],
        ]);

        $this->payrollRuns->createDraft($data);

        return back()->with('success', __('Payroll Run Created As Draft.'));
    }

    public function calculate(PayrollRun $payrollRun): RedirectResponse
    {
        $this->authorize('process', $payrollRun);

        try {
            $this->payrollRuns->calculate($payrollRun);
        } catch (DomainException $e) {
            return back()->with('error', $e->getMessage());
        }

        return back()->with('success', __('Payroll Run Calculated Successfully.'));
    }

    public function submit(PayrollRun $payrollRun): RedirectResponse
    {
        $this->authorize('process', $payrollRun);

        try {
            $this->payrollRuns->submitForApproval($payrollRun, (int) request()->user()->id);
        } catch (DomainException $e) {
            return back()->with('error', $e->getMessage());
        }

        return back()->with('success', __('Payroll Run Submitted.'));
    }

    public function approve(PayrollRun $payrollRun): RedirectResponse
    {
        $this->authorize('approve', $payrollRun);

        try {
            $this->payrollRuns->approve($payrollRun, (int) request()->user()->id);
        } catch (DomainException $e) {
            return back()->with('error', $e->getMessage());
        }

        return back()->with('success', __('Payroll Run Approved (Not Posted).'));
    }

    public function post(PayrollRun $payrollRun): RedirectResponse
    {
        $this->authorize('post', $payrollRun);

        try {
            $this->payrollRuns->post($payrollRun, (int) request()->user()->id);
        } catch (DomainException $e) {
            return back()->with('error', $e->getMessage());
        }

        return back()->with('success', __('Payroll Run Posted To The Ledger.'));
    }

    public function reverse(PayrollRun $payrollRun): RedirectResponse
    {
        $this->authorize('reverse', $payrollRun);

        try {
            $this->payrollRuns->reverse($payrollRun, (int) request()->user()->id);
        } catch (DomainException $e) {
            return back()->with('error', $e->getMessage());
        }

        return back()->with('success', __('Payroll Run Reversed.'));
    }
}
