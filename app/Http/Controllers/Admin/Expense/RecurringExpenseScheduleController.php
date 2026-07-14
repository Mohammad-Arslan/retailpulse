<?php

declare(strict_types=1);

namespace App\Http\Controllers\Admin\Expense;

use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\Expense\StoreRecurringExpenseScheduleRequest;
use App\Models\Branch;
use App\Models\CostCentre;
use App\Models\ExpenseCategory;
use App\Models\OrganizationEntity;
use App\Models\RecurringExpenseSchedule;
use App\Models\TaxType;
use App\Support\ListPagination;
use Carbon\CarbonImmutable;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

final class RecurringExpenseScheduleController extends Controller
{
    public function index(Request $request): Response
    {
        $this->authorize('viewAny', RecurringExpenseSchedule::class);

        $filters = ListPagination::filters($request, ['search', 'status', 'sort', 'direction']);
        $perPage = ListPagination::resolve($filters['per_page']);

        $query = RecurringExpenseSchedule::query()
            ->with(['category:id,name,code', 'branch:id,name'])
            ->when($filters['search'] ?? null, function ($q, string $search): void {
                $q->where(function ($inner) use ($search): void {
                    $inner->whereHas('category', fn ($cat) => $cat->where('name', 'like', "%{$search}%"))
                        ->orWhereHas('branch', fn ($branch) => $branch->where('name', 'like', "%{$search}%"));
                });
            })
            ->when($filters['status'] ?? null, fn ($q, string $status) => $q->where('status', $status))
            ->orderBy($filters['sort'] ?? 'next_run_at', $filters['direction'] ?? 'asc');

        $schedules = $query->paginate($perPage)->withQueryString();

        return Inertia::render('Admin/RecurringExpenses/Index', [
            'schedules' => $schedules->through(fn (RecurringExpenseSchedule $schedule) => [
                'id' => $schedule->id,
                'category' => $schedule->category?->name,
                'branch' => $schedule->branch?->name,
                'amount' => $schedule->amount,
                'currency_code' => $schedule->currency_code,
                'frequency' => $schedule->frequency,
                'interval_count' => $schedule->interval_count,
                'start_date' => $schedule->start_date?->toDateString(),
                'next_run_at' => $schedule->next_run_at?->toDateTimeString(),
                'status' => $schedule->status,
            ]),
            'filters' => $filters,
        ]);
    }

    public function create(): Response
    {
        $this->authorize('create', RecurringExpenseSchedule::class);

        return Inertia::render('Admin/RecurringExpenses/Create', $this->formOptions());
    }

    public function store(StoreRecurringExpenseScheduleRequest $request): RedirectResponse
    {
        $this->authorize('create', RecurringExpenseSchedule::class);

        $data = $request->validated();
        $startDate = CarbonImmutable::parse($data['start_date']);

        RecurringExpenseSchedule::query()->create([
            ...$data,
            'next_run_at' => $startDate->startOfDay(),
            'created_by' => (int) $request->user()->id,
        ]);

        return redirect()
            ->route('admin.expenses.recurring-expenses.index')
            ->with('success', __('Recurring Expense Schedule Created Successfully.'));
    }

    /**
     * @return array<string, mixed>
     */
    private function formOptions(): array
    {
        return [
            'categories' => ExpenseCategory::query()
                ->where('is_group', false)
                ->where('status', 'active')
                ->orderBy('name')
                ->get(['id', 'name', 'code']),
            'branches' => Branch::query()->where('is_active', true)->orderBy('name')->get(['id', 'name', 'code']),
            'legalEntities' => OrganizationEntity::query()
                ->where('status', 'active')
                ->orderBy('legal_name')
                ->get(['id', 'legal_name', 'functional_currency_code']),
            'costCentres' => CostCentre::query()->where('status', 'active')->orderBy('name')->get(['id', 'code', 'name']),
            'taxTypes' => TaxType::query()->where('status', 'active')->orderBy('name')->get(['id', 'name', 'code', 'rate']),
            'paymentMethods' => ['cash', 'card', 'bank_transfer'],
            'frequencies' => ['daily', 'weekly', 'monthly', 'quarterly', 'annual', 'custom_interval'],
            'prorationPolicies' => ['none', 'first_period', 'last_period', 'both'],
            'statuses' => ['active', 'paused', 'cancelled'],
        ];
    }
}
