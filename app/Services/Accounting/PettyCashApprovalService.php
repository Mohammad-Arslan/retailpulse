<?php

declare(strict_types=1);

namespace App\Services\Accounting;

use App\Enums\PettyCashApprovalStatus;
use App\Enums\PettyCashVoucherType;
use App\Models\PettyCashRegister;
use App\Models\PettyCashVoucher;
use App\Models\User;
use DomainException;
use Illuminate\Support\Facades\DB;

final class PettyCashApprovalService
{
    public function __construct(
        private readonly AccountingEventService $accountingEvents,
    ) {}

    public function requiresApproval(PettyCashVoucher $voucher): bool
    {
        $voucher->loadMissing('register');

        $threshold = (float) ($voucher->register?->approval_threshold_amount ?? 0);

        if ($threshold <= 0) {
            return false;
        }

        return (float) $voucher->amount > $threshold;
    }

    public function approve(PettyCashVoucher $voucher, User $approver, ?string $memo = null): PettyCashVoucher
    {
        if ($voucher->approval_status === PettyCashApprovalStatus::Approved) {
            return $voucher;
        }

        if ($voucher->approval_status === PettyCashApprovalStatus::Rejected) {
            throw new DomainException(__('Rejected vouchers must be resubmitted before approval.'));
        }

        return DB::transaction(function () use ($voucher, $approver, $memo) {
            $voucher->loadMissing('register');
            $register = $voucher->register;

            if ($register === null) {
                throw new DomainException(__('Petty cash register not found.'));
            }

            $voucher->update([
                'approval_status' => PettyCashApprovalStatus::Approved,
                'approved_by_user_id' => $approver->id,
                'approved_at' => now(),
                'rejection_reason' => null,
                'description' => $memo ? trim($memo) : $voucher->description,
            ]);

            if ($voucher->journal_entry_id !== null) {
                return $voucher->fresh(['register']);
            }

            $this->postVoucherJournal($voucher, $register, (int) $approver->id);

            if ($voucher->voucher_type === PettyCashVoucherType::Disbursement) {
                $register->update([
                    'current_balance' => (float) $register->current_balance - (float) $voucher->amount,
                ]);
            } elseif ($voucher->voucher_type === PettyCashVoucherType::TopUp) {
                $register->update([
                    'current_balance' => (float) $register->current_balance + (float) $voucher->amount,
                ]);
            }

            return $voucher->fresh(['register']);
        });
    }

    public function reject(PettyCashVoucher $voucher, string $reason): PettyCashVoucher
    {
        if ($voucher->approval_status === PettyCashApprovalStatus::Approved) {
            throw new DomainException(__('Approved vouchers cannot be rejected.'));
        }

        $voucher->update([
            'approval_status' => PettyCashApprovalStatus::Rejected,
            'rejection_reason' => $reason,
            'approved_by_user_id' => null,
            'approved_at' => null,
        ]);

        return $voucher->fresh(['register']);
    }

    private function postVoucherJournal(PettyCashVoucher $voucher, PettyCashRegister $register, int $userId): void
    {
        $eventType = match ($voucher->voucher_type) {
            PettyCashVoucherType::TopUp => 'petty_cash.topped_up',
            PettyCashVoucherType::Disbursement => 'petty_cash.disbursed',
            PettyCashVoucherType::Adjustment => 'petty_cash.adjusted',
        };

        $event = $this->accountingEvents->process(
            $eventType,
            PettyCashVoucher::class,
            $voucher->id,
            [
                'date' => $voucher->date->toDateString(),
                'branch_id' => $register->branch_id,
                'amount' => (float) $voucher->amount,
                'settlement_amount' => (float) $voucher->amount,
                'description' => $voucher->description ?? $eventType,
                'source_number' => $voucher->voucher_number,
                'user_id' => $userId,
            ],
            $userId,
        );

        $voucher->update(['journal_entry_id' => $event->journal_entry_id]);
    }
}
