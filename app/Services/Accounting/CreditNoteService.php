<?php

declare(strict_types=1);

namespace App\Services\Accounting;

use App\DTOs\Accounting\CreateCreditNoteData;
use App\Enums\ArLedgerEntryType;
use App\Enums\CreditNoteStatus;
use App\Events\Accounting\CreditNoteIssued;
use App\Models\CreditNote;
use App\Models\CustomerArLedger;
use App\Models\TaxType;
use App\Services\Customer\CustomerCreditService;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

final class CreditNoteService
{
    public function __construct(
        private readonly DocumentNumberService $documentNumbers,
        private readonly CustomerCreditService $customerCredit,
        private readonly TaxLedgerService $taxLedger,
        private readonly CurrencyConversionService $currencyConversion,
    ) {}

    public function create(CreateCreditNoteData $data, int $userId): CreditNote
    {
        $amount = $data->amount;

        if ($amount <= 0) {
            throw ValidationException::withMessages([
                'amount' => __('Credit note amount must be greater than zero.'),
            ]);
        }

        $customerId = $data->customerId;
        $branchId = $data->branchId;
        $outstanding = $this->customerCredit->getOutstandingBalance($customerId, $branchId);

        if ($amount > $outstanding) {
            throw ValidationException::withMessages([
                'amount' => __('Credit note amount exceeds customer outstanding balance.'),
            ]);
        }

        $taxType = $data->taxTypeId !== null ? TaxType::query()->find($data->taxTypeId) : null;
        $taxAmount = $data->taxAmount > 0
            ? $data->taxAmount
            : ($taxType ? $this->taxLedger->calculateTaxAmount($amount, $taxType) : 0.0);

        $currencyCode = $data->currencyCode ?? $this->currencyConversion->functionalCurrencyCode();
        $fx = $this->currencyConversion->convertToFunctional(
            $amount,
            $currencyCode,
            $data->exchangeRate,
            $data->date,
        );

        return DB::transaction(function () use ($data, $userId, $amount, $taxAmount, $currencyCode, $fx, $customerId, $branchId) {
            $creditNote = CreditNote::query()->create([
                'credit_note_number' => $this->documentNumbers->next('credit_note', 'CN', $branchId),
                'customer_id' => $customerId,
                'branch_id' => $branchId,
                'sale_invoice_id' => $data->saleInvoiceId,
                'date' => $data->date,
                'currency_id' => null,
                'currency_code' => $currencyCode,
                'exchange_rate' => $fx['exchange_rate'],
                'amount' => $amount,
                'tax_amount' => $taxAmount,
                'tax_type_id' => $data->taxTypeId,
                'reason' => $data->reason,
                'status' => CreditNoteStatus::Posted,
                'created_by' => $userId,
            ]);

            $previousBalance = $this->customerCredit->getOutstandingBalance($customerId, $branchId);

            CustomerArLedger::query()->create([
                'customer_id' => $customerId,
                'branch_id' => $branchId,
                'sale_id' => null,
                'entry_type' => ArLedgerEntryType::CreditNote,
                'amount' => $amount,
                'balance_after' => $previousBalance - $amount,
                'reference' => $creditNote->credit_note_number,
                'notes' => $data->reason,
                'user_id' => $userId,
                'created_at' => now(),
            ]);

            CreditNoteIssued::dispatch($creditNote);

            return $creditNote->fresh(['customer', 'branch']);
        });
    }
}
