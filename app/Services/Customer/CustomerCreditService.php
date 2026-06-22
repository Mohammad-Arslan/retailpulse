<?php

declare(strict_types=1);

namespace App\Services\Customer;

use App\Enums\ArLedgerEntryType;
use App\Models\Customer;
use App\Models\CustomerArLedger;
use App\Models\CustomerWriteOff;
use App\Models\Sale;
use App\Models\User;
use App\Services\PosPinService;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

final class CustomerCreditService
{
    public function __construct(
        private readonly PosPinService $posPin,
    ) {}

    public function getOutstandingBalance(int $customerId, ?int $branchId = null): float
    {
        $query = CustomerArLedger::query()
            ->where('customer_id', $customerId)
            ->orderByDesc('created_at')
            ->orderByDesc('id');

        if ($branchId !== null) {
            $query->where('branch_id', $branchId);

            $latest = $query->first();

            return $latest !== null ? max(0, (float) $latest->balance_after) : 0.0;
        }

        return (float) CustomerArLedger::query()
            ->select('branch_id', DB::raw('MAX(id) as latest_id'))
            ->where('customer_id', $customerId)
            ->groupBy('branch_id')
            ->get()
            ->sum(function ($row) {
                $entry = CustomerArLedger::query()->find($row->latest_id);

                return $entry !== null ? max(0, (float) $entry->balance_after) : 0.0;
            });
    }

    public function recordCreditSale(Sale $sale, float $amount, int $userId): CustomerArLedger
    {
        if ($sale->customer_id === null) {
            throw ValidationException::withMessages([
                'customer_id' => __('A customer is required for credit sales.'),
            ]);
        }

        if ($amount <= 0) {
            throw ValidationException::withMessages([
                'amount' => __('Credit amount must be greater than zero.'),
            ]);
        }

        return DB::transaction(function () use ($sale, $amount, $userId) {
            $previousBalance = $this->getOutstandingBalance($sale->customer_id, $sale->branch_id);
            $balanceAfter = $previousBalance + $amount;

            return CustomerArLedger::query()->create([
                'customer_id' => $sale->customer_id,
                'branch_id' => $sale->branch_id,
                'sale_id' => $sale->id,
                'entry_type' => ArLedgerEntryType::Invoice,
                'amount' => $amount,
                'balance_after' => $balanceAfter,
                'reference' => $sale->invoice?->number,
                'notes' => __('Credit sale #:id', ['id' => $sale->id]),
                'user_id' => $userId,
                'created_at' => now(),
            ]);
        });
    }

    public function recordPayment(
        int $customerId,
        int $branchId,
        float $amount,
        int $userId,
        ?string $reference = null,
    ): CustomerArLedger {
        if ($amount <= 0) {
            throw ValidationException::withMessages([
                'amount' => __('Payment amount must be greater than zero.'),
            ]);
        }

        return DB::transaction(function () use ($customerId, $branchId, $amount, $userId, $reference) {
            $previousBalance = $this->getOutstandingBalance($customerId, $branchId);

            if ($amount > $previousBalance) {
                throw ValidationException::withMessages([
                    'amount' => __('Payment exceeds outstanding balance.'),
                ]);
            }

            return CustomerArLedger::query()->create([
                'customer_id' => $customerId,
                'branch_id' => $branchId,
                'sale_id' => null,
                'entry_type' => ArLedgerEntryType::Payment,
                'amount' => $amount,
                'balance_after' => $previousBalance - $amount,
                'reference' => $reference,
                'notes' => null,
                'user_id' => $userId,
                'created_at' => now(),
            ]);
        });
    }

    public function assertCanChargeCredit(
        Customer $customer,
        float $amount,
        int $branchId,
        ?string $managerPin,
        User $cashier,
    ): void {
        if ($amount <= 0) {
            throw ValidationException::withMessages([
                'amount' => __('Credit amount must be greater than zero.'),
            ]);
        }

        $creditLimit = $customer->credit_limit;

        if ($creditLimit === null) {
            throw ValidationException::withMessages([
                'credit_limit' => __('This customer does not have a credit limit configured.'),
            ]);
        }

        $outstanding = $this->getOutstandingBalance($customer->id, $branchId);
        $projected = $outstanding + $amount;

        if ($projected <= (float) $creditLimit) {
            return;
        }

        if ($managerPin === null || $managerPin === '') {
            throw ValidationException::withMessages([
                'credit_limit' => __('Credit limit exceeded. Manager approval is required.'),
            ]);
        }

        if (! $cashier->can('pos.approve-discount')) {
            throw new AuthorizationException(__('Manager approval requires pos.approve-discount permission.'));
        }

        if (! $this->posPin->verifyPin($cashier, $managerPin)) {
            throw ValidationException::withMessages([
                'manager_pin' => __('Invalid manager PIN.'),
            ]);
        }
    }

    public function writeOff(
        int $customerId,
        int $branchId,
        float $amount,
        string $reasonCode,
        User $approver,
        ?string $notes = null,
    ): CustomerWriteOff {
        if (! $approver->can('customers.write-off-debt')) {
            throw new AuthorizationException(__('You are not allowed to write off customer debt.'));
        }

        if ($amount <= 0) {
            throw ValidationException::withMessages([
                'amount' => __('Write-off amount must be greater than zero.'),
            ]);
        }

        return DB::transaction(function () use ($customerId, $branchId, $amount, $reasonCode, $approver, $notes) {
            $previousBalance = $this->getOutstandingBalance($customerId, $branchId);

            if ($amount > $previousBalance) {
                throw ValidationException::withMessages([
                    'amount' => __('Write-off amount exceeds outstanding balance.'),
                ]);
            }

            CustomerArLedger::query()->create([
                'customer_id' => $customerId,
                'branch_id' => $branchId,
                'sale_id' => null,
                'entry_type' => ArLedgerEntryType::WriteOff,
                'amount' => $amount,
                'balance_after' => $previousBalance - $amount,
                'reference' => $reasonCode,
                'notes' => $notes,
                'user_id' => $approver->id,
                'created_at' => now(),
            ]);

            return CustomerWriteOff::query()->create([
                'customer_id' => $customerId,
                'branch_id' => $branchId,
                'amount' => $amount,
                'reason_code' => $reasonCode,
                'approved_by' => $approver->id,
                'approved_at' => now(),
                'notes' => $notes,
            ]);
        });
    }
}
