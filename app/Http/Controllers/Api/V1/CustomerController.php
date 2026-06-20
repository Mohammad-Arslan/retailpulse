<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1;

use App\DTOs\Customer\CreateCustomerData;
use App\Events\CustomerCreditLimitWarning;
use App\Http\Controllers\Controller;
use App\Http\Requests\Api\StoreCustomerRequest;
use App\Models\Customer;
use App\Services\Customer\CustomerCreditService;
use App\Services\Customer\CustomerProfileService;
use App\Services\Customer\CustomerService;
use App\Services\Customer\StoreCreditService;
use App\Services\Customer\WalletService;
use App\Support\BranchContext;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;

final class CustomerController extends Controller
{
    public function __construct(
        private readonly CustomerService $customers,
        private readonly CustomerProfileService $profiles,
        private readonly CustomerCreditService $credit,
        private readonly WalletService $wallet,
        private readonly StoreCreditService $storeCredit,
    ) {}

    public function search(Request $request): JsonResponse
    {
        $user = $request->user();

        if (! $user?->can('pos.access') && ! $user?->can('customers.view')) {
            abort(Response::HTTP_FORBIDDEN);
        }

        $query = (string) $request->query('q', '');
        $context = app(BranchContext::class);
        $branchId = $context->branchId;
        $canViewCredit = $user?->can('customers.view-credit') ?? false;

        $customers = Customer::query()
            ->with(['loyaltyTier', 'wallet'])
            ->where('is_active', true)
            ->when($query !== '', function ($q) use ($query) {
                $q->where(function ($inner) use ($query) {
                    $inner->where('name', 'like', "%{$query}%")
                        ->orWhere('phone', 'like', "%{$query}%")
                        ->orWhere('email', 'like', "%{$query}%");
                });
            })
            ->orderBy('name')
            ->limit(20)
            ->get()
            ->map(function (Customer $customer) use ($branchId, $canViewCredit) {
                $row = [
                    'id' => $customer->id,
                    'name' => $customer->name,
                    'phone' => $customer->phone,
                    'email' => $customer->email,
                    'ntn' => $customer->ntn,
                    'cnic' => $customer->cnic,
                    'loyalty_tier' => $customer->loyaltyTier !== null ? [
                        'id' => $customer->loyaltyTier->id,
                        'name' => $customer->loyaltyTier->name,
                        'points_multiplier' => (float) $customer->loyaltyTier->points_multiplier,
                    ] : null,
                    'wallet_balance' => number_format($this->wallet->getAvailableBalance($customer->id), 2, '.', ''),
                    'wallet_expires_at' => $customer->wallet?->expires_at?->toIso8601String(),
                    'store_credit_balance' => number_format($this->storeCredit->getAvailableBalance($customer->id), 2, '.', ''),
                ];

                if ($canViewCredit) {
                    $row['ar_outstanding'] = number_format(
                        $this->credit->getOutstandingBalance($customer->id, $branchId),
                        2,
                        '.',
                        '',
                    );
                    $row['credit_limit'] = $customer->credit_limit !== null
                        ? number_format((float) $customer->credit_limit, 2, '.', '')
                        : null;
                }

                return $row;
            });

        return response()->json($customers);
    }

    public function show(Request $request, int $customer): JsonResponse
    {
        $user = $request->user();

        if (! $user?->can('pos.access') && ! $user?->can('customers.view')) {
            abort(Response::HTTP_FORBIDDEN);
        }

        $record = Customer::query()->findOrFail($customer);
        $context = app(BranchContext::class);
        $profile = $this->profiles->buildProfile($record, $context->branchId);

        if (! $user?->can('customers.view-credit')) {
            unset($profile['ar_balance'], $profile['credit_limit']);
        }

        return response()->json($profile);
    }

    public function store(StoreCustomerRequest $request): JsonResponse
    {
        $customer = $this->customers->create(new CreateCustomerData(
            name: $request->validated('name'),
            phone: $request->validated('phone'),
            email: $request->validated('email'),
            ntn: null,
            cnic: null,
            isActive: true,
            loyaltyTierId: null,
            customerGroupId: null,
            creditLimit: null,
            preferredPaymentMethod: null,
            notes: null,
        ));

        return response()->json([
            'id' => $customer->id,
            'name' => $customer->name,
            'phone' => $customer->phone,
            'email' => $customer->email,
        ], Response::HTTP_CREATED);
    }

    public function creditCheck(Request $request, int $customer): JsonResponse
    {
        $user = $request->user();

        if (! $user?->can('pos.access') && ! $user?->can('customers.view')) {
            abort(Response::HTTP_FORBIDDEN);
        }

        $request->validate([
            'branch_id' => ['nullable', 'integer', 'exists:branches,id'],
            'amount' => ['nullable', 'numeric', 'min:0'],
        ]);

        $record = Customer::query()->findOrFail($customer);
        $context = app(BranchContext::class);
        $branchId = (int) ($request->query('branch_id') ?? $context->branchId ?? 0);
        $amount = (float) ($request->query('amount') ?? 0);

        if ($branchId <= 0) {
            return response()->json(['message' => __('Branch context is required.')], Response::HTTP_UNPROCESSABLE_ENTITY);
        }

        $outstanding = $this->credit->getOutstandingBalance($record->id, $branchId);
        $creditLimit = $record->credit_limit !== null ? (float) $record->credit_limit : null;
        $projected = $outstanding + $amount;
        $limitExceeded = $creditLimit !== null && $projected > $creditLimit;
        $warningThreshold = $creditLimit !== null && $creditLimit > 0
            && $projected >= ($creditLimit * 0.8);

        if ($limitExceeded || $warningThreshold) {
            event(new CustomerCreditLimitWarning(
                customerId: $record->id,
                branchId: $branchId,
                customerName: $record->name,
                creditLimit: $creditLimit ?? 0,
                outstanding: $outstanding,
                projected: $projected,
                limitExceeded: $limitExceeded,
            ));
        }

        return response()->json([
            'customer_id' => $record->id,
            'branch_id' => $branchId,
            'credit_limit' => $creditLimit !== null ? number_format($creditLimit, 2, '.', '') : null,
            'outstanding' => number_format($outstanding, 2, '.', ''),
            'projected' => number_format($projected, 2, '.', ''),
            'available_credit' => $creditLimit !== null
                ? number_format(max(0, $creditLimit - $outstanding), 2, '.', '')
                : null,
            'limit_exceeded' => $limitExceeded,
            'requires_manager_pin' => $limitExceeded,
        ]);
    }
}
