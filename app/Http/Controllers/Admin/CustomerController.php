<?php

declare(strict_types=1);

namespace App\Http\Controllers\Admin;

use App\DTOs\Customer\CreateCustomerData;
use App\DTOs\Customer\UpdateCustomerData;
use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\StoreCustomerRequest;
use App\Http\Requests\Admin\UpdateCustomerRequest;
use App\Models\Customer;
use App\Models\CustomerArLedger;
use App\Models\CustomerGroup;
use App\Models\CustomerWalletTransaction;
use App\Models\LoyaltyProgram;
use App\Models\LoyaltyTier;
use App\Models\SystemSetting;
use App\Repositories\Contracts\CustomerRepositoryInterface;
use App\Services\Customer\CustomerProfileService;
use App\Services\Customer\CustomerService;
use App\Services\Customer\CustomerStatementService;
use App\Services\Loyalty\LoyaltyProfileService;
use App\Support\BranchContext;
use App\Support\ListPagination;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Storage;
use Inertia\Inertia;
use Inertia\Response;

final class CustomerController extends Controller
{
    public function __construct(
        private readonly CustomerRepositoryInterface $customers,
        private readonly CustomerService $customerService,
        private readonly CustomerProfileService $profileService,
        private readonly CustomerStatementService $statements,
        private readonly LoyaltyProfileService $loyaltyProfile,
    ) {}

    public function index(Request $request): Response
    {
        $this->authorize('viewAny', Customer::class);

        $filters = ListPagination::filters(
            $request,
            ['search', 'is_active', 'loyalty_tier_id', 'customer_group_id', 'sort', 'direction'],
        );

        $paginator = $this->customers->paginate(
            $filters,
            ListPagination::resolve($filters['per_page']),
        );

        $canViewCredit = (bool) $request->user()?->can('customers.view-credit');
        $loyaltyEngineEnabled = (bool) SystemSetting::get('loyalty', 'enabled', true);

        return Inertia::render('Admin/Customers/Index', [
            'customers' => $paginator->through(fn (Customer $customer): array => [
                'id' => $customer->id,
                'name' => $customer->name,
                'phone' => $customer->phone,
                'email' => $customer->email,
                'is_active' => $customer->is_active,
                'loyalty_tier' => ! $loyaltyEngineEnabled && $customer->loyaltyTier ? ['name' => $customer->loyaltyTier->name] : null,
                'customer_group' => $customer->customerGroup ? ['name' => $customer->customerGroup->name] : null,
                'credit_limit' => $canViewCredit && $customer->credit_limit !== null
                    ? number_format((float) $customer->credit_limit, 2, '.', '')
                    : null,
                'created_at' => $customer->created_at?->toIso8601String(),
            ]),
            'filters' => $filters,
            'canViewCredit' => $canViewCredit,
            'legacyLoyaltyEnabled' => ! $loyaltyEngineEnabled,
            'loyaltyTiers' => LoyaltyTier::query()->where('is_active', true)->orderBy('sort_order')->get(['id', 'name']),
            'customerGroups' => CustomerGroup::query()->where('is_active', true)->orderBy('name')->get(['id', 'name']),
        ]);
    }

    public function create(): Response
    {
        $this->authorize('create', Customer::class);
        $loyaltyEngineEnabled = (bool) SystemSetting::get('loyalty', 'enabled', true);

        return Inertia::render('Admin/Customers/Create', [
            'legacyLoyaltyEnabled' => ! $loyaltyEngineEnabled,
            'loyaltyTiers' => LoyaltyTier::query()->where('is_active', true)->orderBy('sort_order')->get(['id', 'name']),
            'customerGroups' => CustomerGroup::query()->where('is_active', true)->orderBy('name')->get(['id', 'name']),
        ]);
    }

    public function store(StoreCustomerRequest $request): RedirectResponse
    {
        $this->authorize('create', Customer::class);

        $customer = $this->customerService->create(CreateCustomerData::fromRequest($request));

        return redirect()
            ->route('admin.customers.show', $customer)
            ->with('success', __('Customer created successfully.'));
    }

    public function show(Request $request, Customer $customer): Response
    {
        $this->authorize('view', $customer);

        $context = app(BranchContext::class);
        $branchId = $context->branchId;
        $profile = $this->profileService->buildProfile($customer, $branchId);
        $canViewCredit = (bool) $request->user()?->can('customers.view-credit');
        $loyaltyEngineEnabled = (bool) SystemSetting::get('loyalty', 'enabled', true);

        $creditLimit = $profile['credit_limit'] ?? null;
        $arOutstanding = $profile['ar_balance'] ?? '0.00';
        $creditAvailable = null;

        if ($creditLimit !== null) {
            $creditAvailable = number_format(
                max(0, (float) $creditLimit - (float) $arOutstanding),
                2,
                '.',
                '',
            );
        }

        $customerData = array_merge($profile['customer'], [
            'loyalty_tier' => ! $loyaltyEngineEnabled ? $profile['loyalty_tier'] : null,
            'customer_group' => $profile['customer_group'],
            'loyalty_points' => ! $loyaltyEngineEnabled ? $profile['metrics']['loyalty_points'] : null,
            'wallet' => $profile['wallet'],
            'store_credit_balance' => $profile['store_credit']['balance'],
            'ar_outstanding' => $canViewCredit ? $arOutstanding : null,
            'credit_limit' => $canViewCredit ? $creditLimit : null,
            'credit_available' => $canViewCredit ? $creditAvailable : null,
        ]);

        $walletTransactions = [];

        if ($customer->wallet !== null) {
            $walletTransactions = CustomerWalletTransaction::query()
                ->where('customer_wallet_id', $customer->wallet->id)
                ->orderByDesc('created_at')
                ->orderByDesc('id')
                ->limit(20)
                ->get()
                ->map(fn (CustomerWalletTransaction $tx): array => [
                    'id' => $tx->id,
                    'type' => $tx->type->value,
                    'reason' => $tx->reason->value,
                    'amount' => number_format((float) $tx->amount, 2, '.', ''),
                    'created_at' => $tx->created_at?->toIso8601String(),
                ])
                ->values()
                ->all();
        }

        $arLedger = [];

        if ($canViewCredit) {
            $ledgerQuery = CustomerArLedger::query()
                ->where('customer_id', $customer->id)
                ->orderByDesc('created_at')
                ->orderByDesc('id')
                ->limit(20);

            if ($branchId !== null) {
                $ledgerQuery->where('branch_id', $branchId);
            }

            $arLedger = $ledgerQuery
                ->get()
                ->map(fn (CustomerArLedger $entry): array => [
                    'id' => $entry->id,
                    'entry_type' => $entry->entry_type->value,
                    'amount' => number_format((float) $entry->amount, 2, '.', ''),
                    'balance_after' => number_format((float) $entry->balance_after, 2, '.', ''),
                    'created_at' => $entry->created_at?->toIso8601String(),
                ])
                ->values()
                ->all();
        }

        $currency = (string) SystemSetting::get('general', 'default_currency', 'PKR');

        $recentSales = collect($profile['recent_sales'])
            ->map(fn (array $sale): array => [
                'id' => $sale['id'],
                'status' => 'completed',
                'grand_total' => $sale['grand_total'],
                'currency' => $currency,
                'completed_at' => $sale['completed_at'],
                'invoice' => isset($sale['invoice_number'])
                    ? ['number' => $sale['invoice_number']]
                    : null,
            ])
            ->values()
            ->all();

        return Inertia::render('Admin/Customers/Show', [
            'customer' => $customerData,
            'stats' => [
                'atv' => $profile['metrics']['average_transaction_value'],
                'sales_count' => $profile['metrics']['transaction_count'],
            ],
            'recentSales' => $recentSales,
            'walletTransactions' => $walletTransactions,
            'arLedger' => $arLedger,
            'currency' => $currency,
            'canViewCredit' => $canViewCredit,
            'legacyLoyaltyEnabled' => ! $loyaltyEngineEnabled,
            'loyalty' => $this->loyaltyProfile->buildLoyaltySummary($customer, $branchId),
            'loyaltyPrograms' => LoyaltyProgram::query()
                ->where('status', 'active')
                ->orderBy('name')
                ->get(['id', 'name']),
        ]);
    }

    public function edit(Customer $customer): Response
    {
        $this->authorize('update', $customer);
        $loyaltyEngineEnabled = (bool) SystemSetting::get('loyalty', 'enabled', true);

        return Inertia::render('Admin/Customers/Edit', [
            'customer' => [
                'id' => $customer->id,
                'name' => $customer->name,
                'phone' => $customer->phone,
                'email' => $customer->email,
                'ntn' => $customer->ntn,
                'cnic' => $customer->cnic,
                'is_active' => $customer->is_active,
                'loyalty_tier_id' => $customer->loyalty_tier_id,
                'customer_group_id' => $customer->customer_group_id,
                'credit_limit' => $customer->credit_limit !== null
                    ? number_format((float) $customer->credit_limit, 2, '.', '')
                    : null,
                'preferred_payment_method' => $customer->preferred_payment_method,
                'notes' => $customer->notes,
            ],
            'legacyLoyaltyEnabled' => ! $loyaltyEngineEnabled,
            'loyaltyTiers' => LoyaltyTier::query()->where('is_active', true)->orderBy('sort_order')->get(['id', 'name']),
            'customerGroups' => CustomerGroup::query()->where('is_active', true)->orderBy('name')->get(['id', 'name']),
        ]);
    }

    public function update(UpdateCustomerRequest $request, Customer $customer): RedirectResponse
    {
        $this->authorize('update', $customer);

        $this->customerService->update($customer, UpdateCustomerData::fromRequest($request));

        return redirect()
            ->route('admin.customers.show', $customer)
            ->with('success', __('Customer updated successfully.'));
    }

    public function destroy(Customer $customer): RedirectResponse
    {
        $this->authorize('delete', $customer);

        $customer->delete();

        return redirect()
            ->route('admin.customers.index')
            ->with('success', __('Customer deleted successfully.'));
    }

    public function sendStatement(Request $request, Customer $customer): RedirectResponse
    {
        $this->authorize('view', $customer);

        if ($customer->email === null || trim($customer->email) === '') {
            return back()->withErrors([
                'email' => __('Customer has no email address on file.'),
            ]);
        }

        $context = app(BranchContext::class);
        $path = $this->statements->generate($customer, $context->branchId);

        Mail::raw(
            __('Please find your account statement attached.'),
            function ($message) use ($customer, $path) {
                $message->to($customer->email)
                    ->subject(__('Account statement — :name', ['name' => $customer->name]))
                    ->attach(Storage::disk('local')->path($path), [
                        'as' => 'statement.pdf',
                        'mime' => 'application/pdf',
                    ]);
            },
        );

        return back()->with('success', __('Statement emailed to :email.', ['email' => $customer->email]));
    }
}
