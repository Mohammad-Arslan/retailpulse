<?php

declare(strict_types=1);

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\StoreSupplierRequest;
use App\Http\Requests\Admin\UpdateSupplierRequest;
use App\Models\Supplier;
use App\Repositories\Contracts\SupplierRepositoryInterface;
use App\Services\Procurement\ProcurementConfigService;
use App\Services\Procurement\SupplierLedgerService;
use App\Services\Procurement\SupplierService;
use App\Services\Procurement\SupplierStatementService;
use App\Support\BranchContext;
use App\Support\BranchOperationalOptions;
use App\Support\ListPagination;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Storage;
use Inertia\Inertia;
use Inertia\Response;
use Symfony\Component\HttpFoundation\BinaryFileResponse;

final class SupplierController extends Controller
{
    public function __construct(
        private readonly SupplierRepositoryInterface $suppliers,
        private readonly SupplierService $supplierService,
        private readonly SupplierLedgerService $ledger,
        private readonly SupplierStatementService $statements,
        private readonly ProcurementConfigService $procurementConfig,
    ) {}

    public function index(Request $request): Response
    {
        $this->authorize('viewAny', Supplier::class);

        $filters = ListPagination::filters($request, ['search', 'is_active', 'sort', 'direction']);
        $paginator = $this->suppliers->paginate($filters, ListPagination::resolve($filters['per_page']));

        return Inertia::render('Admin/Suppliers/Index', [
            'suppliers' => $paginator->through(fn (Supplier $s) => [
                'id' => $s->id,
                'code' => $s->code,
                'name' => $s->name,
                'email' => $s->email,
                'phone' => $s->phone,
                'is_active' => $s->is_active,
                'balance' => number_format((float) $s->balance, 2, '.', ''),
                'purchase_orders_count' => $s->purchase_orders_count ?? 0,
            ]),
            'filters' => $filters,
        ]);
    }

    public function create(): Response
    {
        $this->authorize('create', Supplier::class);

        return Inertia::render('Admin/Suppliers/Create', [
            'currencies' => BranchOperationalOptions::currencyOptions(),
            'defaultCurrency' => BranchOperationalOptions::defaultCurrency(),
        ]);
    }

    public function store(StoreSupplierRequest $request): RedirectResponse
    {
        $this->authorize('create', Supplier::class);

        $supplier = $this->supplierService->create(
            $request->validated(),
            $request->validated('contacts', []),
            $request->validated('addresses', []),
            (int) $request->user()->id,
        );

        return redirect()->route('admin.suppliers.show', $supplier)
            ->with('success', __('Supplier created successfully.'));
    }

    public function show(Supplier $supplier): Response
    {
        $this->authorize('view', $supplier);

        $supplier = $this->suppliers->findById($supplier->id) ?? $supplier;

        $branchId = app(BranchContext::class)->branchId;
        $ledgerEntries = $this->ledger->statement($supplier->id, $branchId);
        $displayBalance = $this->ledger->getBalance($supplier->id, $branchId);
        $config = $this->procurementConfig->resolve($branchId);

        return Inertia::render('Admin/Suppliers/Show', [
            'supplier' => [
                'id' => $supplier->id,
                'code' => $supplier->code,
                'name' => $supplier->name,
                'email' => $supplier->email,
                'phone' => $supplier->phone,
                'tax_registration_no' => $supplier->tax_registration_no,
                'payment_terms_days' => $supplier->payment_terms_days,
                'credit_terms_days' => $supplier->credit_terms_days,
                'currency_code' => $supplier->currency_code,
                'balance' => number_format($displayBalance, 2, '.', ''),
                'notes' => $supplier->notes,
                'is_active' => $supplier->is_active,
                'on_time_delivery_rate' => $supplier->on_time_delivery_rate,
                'quality_rejection_rate' => $supplier->quality_rejection_rate,
                'last_scored_at' => $supplier->last_scored_at?->toIso8601String(),
                'contacts' => $supplier->contacts,
                'addresses' => $supplier->addresses,
            ],
            'ledgerEntries' => $ledgerEntries->map(fn ($e) => [
                'id' => $e->id,
                'entry_type' => $e->entry_type->value,
                'amount' => number_format((float) $e->amount, 2, '.', ''),
                'balance_after' => number_format((float) $e->balance_after, 2, '.', ''),
                'reference_no' => $e->reference_no,
                'created_at' => $e->created_at?->toIso8601String(),
            ]),
            'branchId' => $branchId,
            'paymentMethods' => $config['payment_methods'] ?? ['cash', 'bank_transfer'],
        ]);
    }

    public function deactivate(Supplier $supplier): RedirectResponse
    {
        $this->authorize('deactivate', $supplier);

        $supplier->update(['is_active' => false, 'updated_by' => auth()->id()]);

        return back()->with('success', __('Supplier deactivated.'));
    }

    public function statementPdf(Supplier $supplier): BinaryFileResponse
    {
        $this->authorize('view', $supplier);

        $branchId = app(BranchContext::class)->branchId;
        $path = $this->statements->generate($supplier, $branchId);

        return response()->file(Storage::disk('local')->path($path), [
            'Content-Type' => 'application/pdf',
            'Content-Disposition' => 'inline; filename="supplier-statement.pdf"',
        ]);
    }

    public function sendStatement(Request $request, Supplier $supplier): RedirectResponse
    {
        $this->authorize('view', $supplier);

        if ($supplier->email === null || trim($supplier->email) === '') {
            return back()->withErrors(['email' => __('Supplier has no email address.')]);
        }

        $branchId = app(BranchContext::class)->branchId;
        $path = $this->statements->generate($supplier, $branchId);

        Mail::raw(
            __('Please find your supplier statement attached.'),
            function ($message) use ($supplier, $path) {
                $message->to($supplier->email)
                    ->subject(__('Supplier statement — :name', ['name' => $supplier->name]))
                    ->attach(Storage::disk('local')->path($path), [
                        'as' => 'statement.pdf',
                        'mime' => 'application/pdf',
                    ]);
            },
        );

        return back()->with('success', __('Statement emailed to :email.', ['email' => $supplier->email]));
    }

    public function edit(Supplier $supplier): Response
    {
        $this->authorize('update', $supplier);

        $supplier = $this->suppliers->findById($supplier->id);

        return Inertia::render('Admin/Suppliers/Edit', [
            'supplier' => $supplier,
            'currencies' => BranchOperationalOptions::currencyOptions(),
        ]);
    }

    public function update(UpdateSupplierRequest $request, Supplier $supplier): RedirectResponse
    {
        $this->authorize('update', $supplier);

        $validated = $request->validated();

        $this->supplierService->update(
            $supplier,
            collect($validated)->except(['contacts', 'addresses'])->all(),
            (int) $request->user()->id,
            $validated['contacts'] ?? null,
            $validated['addresses'] ?? null,
        );

        return redirect()->route('admin.suppliers.show', $supplier)
            ->with('success', __('Supplier updated successfully.'));
    }

    public function destroy(Supplier $supplier): RedirectResponse
    {
        $this->authorize('delete', $supplier);

        $supplier->delete();

        return redirect()->route('admin.suppliers.index')
            ->with('success', __('Supplier deleted.'));
    }
}
