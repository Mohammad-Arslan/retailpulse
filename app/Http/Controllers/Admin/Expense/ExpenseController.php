<?php

declare(strict_types=1);

namespace App\Http\Controllers\Admin\Expense;

use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\Expense\AttachExpenseReceiptRequest;
use App\Http\Requests\Admin\Expense\StoreExpenseRequest;
use App\Models\Branch;
use App\Models\CostCentre;
use App\Models\Expense;
use App\Models\ExpenseCategory;
use App\Models\OrganizationEntity;
use App\Models\TaxType;
use App\Services\Expense\ExpenseService;
use App\Support\ListPagination;
use DomainException;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

final class ExpenseController extends Controller
{
    public function __construct(
        private readonly ExpenseService $expenses,
    ) {}

    public function index(Request $request): Response
    {
        $this->authorize('viewAny', Expense::class);

        $filters = ListPagination::filters($request, ['search', 'status', 'sort', 'direction']);
        $perPage = ListPagination::resolve($filters['per_page']);

        $query = Expense::query()
            ->with(['category:id,name,code', 'branch:id,name'])
            ->when($filters['search'] ?? null, function ($q, string $search): void {
                $q->where(function ($inner) use ($search): void {
                    $inner->where('expense_number', 'like', "%{$search}%")
                        ->orWhere('description', 'like', "%{$search}%");
                });
            })
            ->when($filters['status'] ?? null, fn ($q, string $status) => $q->where('status', $status))
            ->orderBy($filters['sort'] ?? 'expense_date', $filters['direction'] ?? 'desc');

        $expenses = $query->paginate($perPage)->withQueryString();

        return Inertia::render('Admin/Expenses/Index', [
            'expenses' => $expenses->through(fn (Expense $e) => [
                'id' => $e->id,
                'expense_number' => $e->expense_number,
                'category' => $e->category?->name,
                'branch' => $e->branch?->name,
                'amount' => $e->amount,
                'currency_code' => $e->currency_code,
                'expense_date' => $e->expense_date?->toDateString(),
                'status' => $e->status,
                'approval_required' => $e->approval_required,
            ]),
            'filters' => $filters,
        ]);
    }

    public function create(): Response
    {
        $this->authorize('create', Expense::class);

        return Inertia::render('Admin/Expenses/Create', $this->formOptions());
    }

    public function store(StoreExpenseRequest $request): RedirectResponse
    {
        $this->authorize('create', Expense::class);

        try {
            $expense = $this->expenses->create(
                $request->validated(),
                (int) $request->user()->id,
            );
        } catch (DomainException $e) {
            return back()->withInput()->with('error', $e->getMessage());
        }

        return redirect()
            ->route('admin.expenses.expenses.show', $expense)
            ->with('success', __('Expense Created Successfully.'));
    }

    public function show(Expense $expense): Response
    {
        $this->authorize('view', $expense);

        $expense->load(['category', 'branch', 'legalEntity', 'costCentre', 'taxType', 'attachments', 'approvedByUser']);

        return Inertia::render('Admin/Expenses/Show', [
            'expense' => $this->present($expense),
        ]);
    }

    public function approve(Request $request, Expense $expense): RedirectResponse
    {
        $this->authorize('approve', $expense);

        try {
            $this->expenses->approve($expense, (int) $request->user()->id);
        } catch (DomainException $e) {
            return back()->with('error', $e->getMessage());
        }

        return back()->with('success', __('Expense Approved And Posted Successfully.'));
    }

    public function attachReceipt(AttachExpenseReceiptRequest $request, Expense $expense): RedirectResponse
    {
        $this->authorize('attachReceipt', $expense);

        $this->expenses->attachReceipt(
            $expense,
            $request->file('receipt'),
            (int) $request->user()->id,
        );

        return back()->with('success', __('Receipt Attached Successfully.'));
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
                ->get(['id', 'name', 'code', 'requires_receipt', 'default_tax_type_id']),
            'branches' => Branch::query()->where('is_active', true)->orderBy('name')->get(['id', 'name', 'code']),
            'legalEntities' => OrganizationEntity::query()
                ->where('status', 'active')
                ->orderBy('legal_name')
                ->get(['id', 'legal_name', 'functional_currency_code']),
            'costCentres' => CostCentre::query()->where('status', 'active')->orderBy('name')->get(['id', 'code', 'name']),
            'taxTypes' => TaxType::query()->where('status', 'active')->orderBy('name')->get(['id', 'name', 'code', 'rate']),
            'paymentMethods' => ['cash', 'card', 'bank_transfer'],
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function present(Expense $expense): array
    {
        return [
            'id' => $expense->id,
            'expense_number' => $expense->expense_number,
            'expense_category_id' => $expense->expense_category_id,
            'category' => $expense->category?->name,
            'branch_id' => $expense->branch_id,
            'branch' => $expense->branch?->name,
            'legal_entity_id' => $expense->legal_entity_id,
            'legal_entity' => $expense->legalEntity?->legal_name,
            'cost_centre' => $expense->costCentre?->name,
            'currency_code' => $expense->currency_code,
            'exchange_rate' => $expense->exchange_rate,
            'amount' => $expense->amount,
            'tax_type_id' => $expense->tax_type_id,
            'tax_type' => $expense->taxType?->name,
            'tax_amount' => $expense->tax_amount,
            'functional_amount' => $expense->functional_amount,
            'expense_date' => $expense->expense_date?->toDateString(),
            'payment_method' => $expense->payment_method,
            'description' => $expense->description,
            'status' => $expense->status,
            'approval_required' => $expense->approval_required,
            'approved_by' => $expense->approvedByUser?->name,
            'approved_at' => $expense->approved_at?->toDateTimeString(),
            'journal_entry_id' => $expense->journal_entry_id,
            'attachments' => $expense->attachments->map(fn ($a) => [
                'id' => $a->id,
                'original_name' => $a->original_name,
                'mime' => $a->mime,
                'size' => $a->size,
                'created_at' => $a->created_at?->toDateTimeString(),
            ])->all(),
        ];
    }
}
