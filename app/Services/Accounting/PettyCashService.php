<?php

declare(strict_types=1);

namespace App\Services\Accounting;

use App\Enums\PettyCashApprovalStatus;
use App\Enums\PettyCashVoucherType;
use App\Models\PettyCashRegister;
use App\Models\PettyCashVoucher;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

final class PettyCashService
{
    public function __construct(
        private readonly DocumentNumberService $documentNumbers,
        private readonly AccountingEventService $accountingEvents,
        private readonly PettyCashApprovalService $approvalService,
    ) {}

    /**
     * @param  array<string, mixed>  $data
     */
    public function createVoucher(PettyCashRegister $register, array $data, int $userId): PettyCashVoucher
    {
        $amount = (float) ($data['amount'] ?? 0);
        $type = PettyCashVoucherType::from($data['voucher_type']);

        if ($amount <= 0) {
            throw ValidationException::withMessages([
                'amount' => __('Voucher amount must be greater than zero.'),
            ]);
        }

        if ($type === PettyCashVoucherType::Disbursement && $amount > (float) $register->current_balance) {
            throw ValidationException::withMessages([
                'amount' => __('Insufficient petty cash balance.'),
            ]);
        }

        return DB::transaction(function () use ($register, $data, $userId, $amount, $type) {
            $voucher = PettyCashVoucher::query()->create([
                'voucher_number' => $this->documentNumbers->next('petty_cash_voucher', 'PCV', $register->branch_id),
                'petty_cash_register_id' => $register->id,
                'voucher_type' => $type,
                'date' => $data['date'] ?? now()->toDateString(),
                'amount' => $amount,
                'expense_account_id' => $data['expense_account_id'] ?? null,
                'description' => $data['description'] ?? null,
                'approval_required' => false,
                'approval_status' => PettyCashApprovalStatus::Approved,
                'created_by' => $userId,
            ]);

            $needsApproval = $this->approvalService->requiresApproval($voucher);

            if ($needsApproval) {
                $voucher->update([
                    'approval_required' => true,
                    'approval_status' => PettyCashApprovalStatus::Pending,
                ]);

                return $voucher->fresh(['register']);
            }

            $balanceDelta = match ($type) {
                PettyCashVoucherType::TopUp => $amount,
                PettyCashVoucherType::Disbursement => -$amount,
                PettyCashVoucherType::Adjustment => (float) ($data['adjustment_delta'] ?? 0),
            };

            $register->update([
                'current_balance' => (float) $register->current_balance + $balanceDelta,
            ]);

            $eventType = match ($type) {
                PettyCashVoucherType::TopUp => 'petty_cash.topped_up',
                PettyCashVoucherType::Disbursement => 'petty_cash.disbursed',
                PettyCashVoucherType::Adjustment => 'petty_cash.adjusted',
            };

            try {
                $event = $this->accountingEvents->process(
                    $eventType,
                    PettyCashVoucher::class,
                    $voucher->id,
                    [
                        'date' => $voucher->date->toDateString(),
                        'branch_id' => $register->branch_id,
                        'amount' => $amount,
                        'settlement_amount' => $amount,
                        'description' => $voucher->description ?? $eventType,
                        'source_number' => $voucher->voucher_number,
                        'user_id' => $userId,
                    ],
                    $userId,
                );

                $voucher->update(['journal_entry_id' => $event->journal_entry_id]);
            } catch (\Throwable) {
                // Accounting posting is optional until rules are configured.
            }

            return $voucher->fresh(['register']);
        });
    }
}
