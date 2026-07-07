<?php

declare(strict_types=1);

namespace App\Services\Accounting;

use App\DTOs\Accounting\CreateChequeData;
use App\DTOs\Accounting\UpdateChequeStatusData;
use App\Enums\ChequeStatus;
use App\Enums\ChequeType;
use App\Models\Cheque;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

final class ChequeService
{
    public function __construct(
        private readonly AccountingEventService $accountingEvents,
        private readonly CurrencyConversionService $currencyConversion,
    ) {}

    public function create(CreateChequeData $data, int $userId): Cheque
    {
        $amount = $data->amount;

        if ($amount <= 0) {
            throw ValidationException::withMessages([
                'amount' => __('Cheque amount must be greater than zero.'),
            ]);
        }

        $currencyCode = $data->currencyCode ?? $this->currencyConversion->functionalCurrencyCode();
        $fx = $this->currencyConversion->convertToFunctional(
            $amount,
            $currencyCode,
            null,
            $data->dueDate ?? now()->toDateString(),
        );

        $cheque = Cheque::query()->create([
            'type' => ChequeType::from($data->type),
            'party_type' => $data->partyType,
            'party_id' => $data->partyId,
            'amount' => $amount,
            'currency_id' => null,
            'currency_code' => $currencyCode,
            'exchange_rate' => $fx['exchange_rate'],
            'cheque_no' => $data->chequeNo,
            'bank' => $data->bank,
            'due_date' => $data->dueDate,
            'status' => ChequeStatus::Pending,
            'branch_id' => $data->branchId,
            'created_by' => $userId,
        ]);

        if ($cheque->type === ChequeType::Received) {
            try {
                $event = $this->accountingEvents->process(
                    'cheque.received',
                    Cheque::class,
                    $cheque->id,
                    [
                        'date' => now()->toDateString(),
                        'branch_id' => $cheque->branch_id,
                        'settlement_amount' => (float) $cheque->amount,
                        'party_type' => $cheque->party_type,
                        'party_id' => $cheque->party_id,
                        'user_id' => $userId,
                    ],
                    $userId,
                );

                $cheque->update(['related_journal_entry_id' => $event->journal_entry_id]);
            } catch (\Throwable) {
            }
        }

        return $cheque;
    }

    public function updateStatus(Cheque $cheque, UpdateChequeStatusData $data, int $userId): Cheque
    {
        return $this->applyStatus($cheque, $data->status, $userId);
    }

    public function applyStatus(Cheque $cheque, ChequeStatus $status, int $userId): Cheque
    {
        $eventType = match ($status) {
            ChequeStatus::Deposited => 'cheque.deposited',
            ChequeStatus::Cleared => 'cheque.cleared',
            ChequeStatus::Bounced => 'cheque.bounced',
            default => null,
        };

        return DB::transaction(function () use ($cheque, $status, $userId, $eventType) {
            $cheque->update(['status' => $status]);

            if ($eventType !== null) {
                try {
                    $event = $this->accountingEvents->process(
                        $eventType,
                        Cheque::class,
                        $cheque->id,
                        [
                            'date' => now()->toDateString(),
                            'branch_id' => $cheque->branch_id,
                            'amount' => (float) $cheque->amount,
                            'settlement_amount' => (float) $cheque->amount,
                            'currency_code' => $cheque->currency_code,
                            'exchange_rate' => $cheque->exchange_rate,
                            'party_type' => $cheque->party_type,
                            'party_id' => $cheque->party_id,
                            'description' => "Cheque {$cheque->cheque_no} — {$status->value}",
                            'user_id' => $userId,
                        ],
                        $userId,
                    );

                    $cheque->update(['related_journal_entry_id' => $event->journal_entry_id]);
                } catch (\Throwable) {
                    // Optional until posting rules exist.
                }
            }

            return $cheque->fresh();
        });
    }
}
