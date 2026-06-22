<?php

declare(strict_types=1);

namespace App\Services\Procurement;

use App\Enums\SupplierLedgerEntryType;
use App\Models\Supplier;
use App\Models\SupplierInvoice;
use App\Models\SupplierLedgerEntry;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Facades\DB;

final class SupplierLedgerService
{
    public function getBalance(int $supplierId, ?int $branchId = null): float
    {
        $query = SupplierLedgerEntry::query()
            ->where('supplier_id', $supplierId)
            ->orderByDesc('created_at')
            ->orderByDesc('id');

        if ($branchId !== null) {
            $query->where('branch_id', $branchId);
            $latest = $query->first();

            return $latest !== null ? (float) $latest->balance_after : 0.0;
        }

        return (float) SupplierLedgerEntry::query()
            ->select('branch_id', DB::raw('MAX(id) as latest_id'))
            ->where('supplier_id', $supplierId)
            ->groupBy('branch_id')
            ->get()
            ->sum(function ($row) {
                $entry = SupplierLedgerEntry::query()->find($row->latest_id);

                return $entry !== null ? (float) $entry->balance_after : 0.0;
            });
    }

    public function recordEntry(
        int $supplierId,
        int $branchId,
        SupplierLedgerEntryType $type,
        float $amount,
        string $currencyCode,
        float $exchangeRate,
        float $functionalAmount,
        ?string $referenceType,
        ?int $referenceId,
        ?string $referenceNo,
        ?string $notes,
        int $userId,
    ): SupplierLedgerEntry {
        return DB::transaction(function () use (
            $supplierId, $branchId, $type, $amount, $currencyCode,
            $exchangeRate, $functionalAmount, $referenceType, $referenceId,
            $referenceNo, $notes, $userId
        ) {
            $previousBalance = $this->getBalance($supplierId, $branchId);
            $isCredit = in_array($type, [SupplierLedgerEntryType::Payment, SupplierLedgerEntryType::DebitNote], true);
            $balanceAfter = $isCredit
                ? $previousBalance - abs($amount)
                : $previousBalance + abs($amount);

            $entry = SupplierLedgerEntry::query()->create([
                'supplier_id' => $supplierId,
                'branch_id' => $branchId,
                'entry_type' => $type,
                'amount' => $amount,
                'balance_after' => max(0, $balanceAfter),
                'currency_code' => $currencyCode,
                'exchange_rate' => $exchangeRate,
                'functional_amount' => $functionalAmount,
                'reference_type' => $referenceType,
                'reference_id' => $referenceId,
                'reference_no' => $referenceNo,
                'notes' => $notes,
                'user_id' => $userId,
                'created_at' => now(),
            ]);

            Supplier::query()->where('id', $supplierId)->update([
                'balance' => $this->getBalance($supplierId),
            ]);

            return $entry;
        });
    }

    public function recordInvoiceIfMissing(SupplierInvoice $invoice, int $userId): ?SupplierLedgerEntry
    {
        $exists = SupplierLedgerEntry::query()
            ->where('supplier_id', $invoice->supplier_id)
            ->where('branch_id', $invoice->branch_id)
            ->where('reference_type', SupplierInvoice::class)
            ->where('reference_id', $invoice->id)
            ->exists();

        if ($exists) {
            return null;
        }

        return $this->recordEntry(
            supplierId: $invoice->supplier_id,
            branchId: $invoice->branch_id,
            type: SupplierLedgerEntryType::Invoice,
            amount: (float) $invoice->total,
            currencyCode: $invoice->currency_code,
            exchangeRate: (float) $invoice->exchange_rate,
            functionalAmount: (float) $invoice->functional_total,
            referenceType: SupplierInvoice::class,
            referenceId: $invoice->id,
            referenceNo: $invoice->reference_no,
            notes: __('Supplier invoice :ref', ['ref' => $invoice->reference_no]),
            userId: $userId,
        );
    }

    /**
     * @return Collection<int, SupplierLedgerEntry>
     */
    public function statement(int $supplierId, ?int $branchId = null, ?string $from = null, ?string $to = null)
    {
        $query = SupplierLedgerEntry::query()
            ->where('supplier_id', $supplierId)
            ->orderBy('created_at')
            ->orderBy('id');

        if ($branchId !== null) {
            $query->where('branch_id', $branchId);
        }

        if ($from !== null) {
            $query->whereDate('created_at', '>=', $from);
        }

        if ($to !== null) {
            $query->whereDate('created_at', '<=', $to);
        }

        return $query->get();
    }
}
