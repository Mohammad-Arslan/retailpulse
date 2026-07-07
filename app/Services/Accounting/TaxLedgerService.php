<?php

declare(strict_types=1);

namespace App\Services\Accounting;

use App\Enums\JournalEntryStatus;
use App\Models\JournalTransaction;
use App\Models\TaxType;
use Illuminate\Support\Collection;

final class TaxLedgerService
{
    /**
     * @param  array{branch_id?: int|null, tax_type_id?: int|null, legal_entity_id?: int|null}  $filters
     * @return Collection<int, array{tax_type_id: int|null, tax_type_code: string|null, tax_type_name: string|null, output_tax: float, input_tax: float, net_payable: float}>
     */
    public function summarize(string $dateFrom, string $dateTo, array $filters = []): Collection
    {
        $query = JournalTransaction::query()
            ->select('journal_transactions.*')
            ->join('journal_entries', 'journal_entries.id', '=', 'journal_transactions.journal_entry_id')
            ->where('journal_entries.status', JournalEntryStatus::Posted)
            ->whereDate('journal_entries.journal_date', '>=', $dateFrom)
            ->whereDate('journal_entries.journal_date', '<=', $dateTo)
            ->whereNotNull('journal_transactions.tax_type_id');

        if (! empty($filters['branch_id'])) {
            $query->where('journal_transactions.branch_id', $filters['branch_id']);
        }

        if (! empty($filters['tax_type_id'])) {
            $query->where('journal_transactions.tax_type_id', $filters['tax_type_id']);
        }

        $lines = $query->with('account')->get();

        return $lines
            ->groupBy('tax_type_id')
            ->map(function (Collection $group, $taxTypeId) {
                $taxType = TaxType::query()->find($taxTypeId);
                $outputTax = 0.0;
                $inputTax = 0.0;

                foreach ($group as $line) {
                    $credit = (float) $line->credit;
                    $debit = (float) $line->debit;

                    if ($credit > 0) {
                        $outputTax += $credit;
                    }

                    if ($debit > 0) {
                        $inputTax += $debit;
                    }
                }

                return [
                    'tax_type_id' => $taxTypeId ? (int) $taxTypeId : null,
                    'tax_type_code' => $taxType?->code,
                    'tax_type_name' => $taxType?->name,
                    'output_tax' => round($outputTax, 2),
                    'input_tax' => round($inputTax, 2),
                    'net_payable' => round($outputTax - $inputTax, 2),
                ];
            })
            ->values();
    }

    public function calculateTaxAmount(float $netAmount, TaxType $taxType): float
    {
        $rate = (float) $taxType->rate / 100;

        if ($taxType->calculation_method->value === 'inclusive') {
            return round($netAmount - ($netAmount / (1 + $rate)), 2);
        }

        return round($netAmount * $rate, 2);
    }
}
