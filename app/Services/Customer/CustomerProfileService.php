<?php

declare(strict_types=1);

namespace App\Services\Customer;

use App\Enums\PaymentMethod;
use App\Enums\SaleStatus;
use App\Models\Customer;
use App\Models\Sale;
use App\Models\SalePayment;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

final class CustomerProfileService
{
    public function __construct(
        private readonly LoyaltyService $loyalty,
        private readonly WalletService $wallet,
        private readonly StoreCreditService $storeCredit,
        private readonly CustomerCreditService $credit,
    ) {}

    /**
     * @return array<string, mixed>
     */
    public function buildProfile(Customer $customer, ?int $branchId = null): array
    {
        $customer->load(['loyaltyTier', 'customerGroup', 'wallet']);

        $salesQuery = Sale::query()
            ->where('customer_id', $customer->id)
            ->where('status', SaleStatus::Completed);

        if ($branchId !== null) {
            $salesQuery->where('branch_id', $branchId);
        }

        $transactionCount = (clone $salesQuery)->count();
        $totalSpent = (float) (clone $salesQuery)->sum('grand_total');
        $atv = $transactionCount > 0 ? round($totalSpent / $transactionCount, 2) : 0.0;

        $recentSales = (clone $salesQuery)
            ->with(['branch', 'invoice'])
            ->orderByDesc('completed_at')
            ->limit(10)
            ->get()
            ->map(fn (Sale $sale) => [
                'id' => $sale->id,
                'branch' => $sale->branch?->name,
                'grand_total' => number_format((float) $sale->grand_total, 2, '.', ''),
                'completed_at' => $sale->completed_at?->toIso8601String(),
                'invoice_number' => $sale->invoice?->number,
            ]);

        return [
            'customer' => [
                'id' => $customer->id,
                'name' => $customer->name,
                'phone' => $customer->phone,
                'email' => $customer->email,
                'ntn' => $customer->ntn,
                'cnic' => $customer->cnic,
                'is_active' => $customer->is_active,
                'notes' => $customer->notes,
            ],
            'loyalty_tier' => $customer->loyaltyTier !== null ? [
                'id' => $customer->loyaltyTier->id,
                'name' => $customer->loyaltyTier->name,
                'points_multiplier' => (float) $customer->loyaltyTier->points_multiplier,
            ] : null,
            'customer_group' => $customer->customerGroup !== null ? [
                'id' => $customer->customerGroup->id,
                'name' => $customer->customerGroup->name,
            ] : null,
            'metrics' => [
                'transaction_count' => $transactionCount,
                'average_transaction_value' => number_format($atv, 2, '.', ''),
                'total_spent' => number_format($totalSpent, 2, '.', ''),
                'loyalty_points' => $this->loyalty->getTotalPoints($customer->id),
            ],
            'preferred_payment_method' => $this->resolvePreferredPaymentMethod($customer, $salesQuery),
            'wallet' => [
                'balance' => number_format($this->wallet->getAvailableBalance($customer->id), 2, '.', ''),
                'expires_at' => $customer->wallet?->expires_at?->toIso8601String(),
            ],
            'store_credit' => [
                'balance' => number_format($this->storeCredit->getAvailableBalance($customer->id), 2, '.', ''),
            ],
            'ar_balance' => number_format(
                $this->credit->getOutstandingBalance($customer->id, $branchId),
                2,
                '.',
                '',
            ),
            'credit_limit' => $customer->credit_limit !== null
                ? number_format((float) $customer->credit_limit, 2, '.', '')
                : null,
            'recent_sales' => $recentSales,
        ];
    }

    /**
     * @param  Builder<Sale>  $salesQuery
     */
    private function resolvePreferredPaymentMethod(Customer $customer, $salesQuery): ?string
    {
        if ($customer->preferred_payment_method !== null) {
            return $customer->preferred_payment_method;
        }

        $saleIds = (clone $salesQuery)->pluck('id');

        if ($saleIds->isEmpty()) {
            return null;
        }

        /** @var Collection<int, object{method: string, total: float}> $methods */
        $methods = SalePayment::query()
            ->select('method', DB::raw('SUM(amount) as total'))
            ->whereIn('sale_id', $saleIds)
            ->groupBy('method')
            ->orderByDesc('total')
            ->get();

        $top = $methods->first();

        if ($top === null) {
            return null;
        }

        $method = $top->method;

        return $method instanceof PaymentMethod ? $method->value : (string) $method;
    }
}
