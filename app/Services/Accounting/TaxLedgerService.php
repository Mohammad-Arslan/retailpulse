<?php

declare(strict_types=1);

namespace App\Services\Accounting;

use App\Enums\JournalEntryStatus;
use App\Enums\TaxCalculationMethod;
use App\Models\FiscalYear;
use App\Models\JournalTransaction;
use App\Models\TaxType;
use Carbon\CarbonInterface;
use Illuminate\Support\Collection;

final class TaxLedgerService
{
    public function __construct(
        private readonly FinancialSettingsService $financialSettings,
    ) {}

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

    /**
     * @return array{gross_amount: float, net_amount: float, tax_amount: float, taxable_amount: float}
     */
    public function calculateTax(float $amount, TaxType $taxType, string $calculationContext = 'sales'): array
    {
        $rate = (float) $taxType->rate / 100;

        if ($taxType->calculation_method === TaxCalculationMethod::Inclusive) {
            $taxAmount = round($amount - ($amount / (1 + $rate)), 2);
            $netAmount = round($amount - $taxAmount, 2);
            $grossAmount = round($amount, 2);
        } else {
            $netAmount = round($amount, 2);
            $taxAmount = round($amount * $rate, 2);
            $grossAmount = round($amount + $taxAmount, 2);
        }

        $taxableAmount = $taxAmount;

        if ($calculationContext === 'purchase' && (float) $taxType->recoverable_percentage < 100) {
            $taxableAmount = round($taxAmount * ((float) $taxType->recoverable_percentage / 100), 2);
        }

        return [
            'gross_amount' => $grossAmount,
            'net_amount' => $netAmount,
            'tax_amount' => $taxAmount,
            'taxable_amount' => $taxableAmount,
        ];
    }

    public function calculateTaxAmount(float $netAmount, TaxType $taxType): float
    {
        return $this->calculateTax($netAmount, $taxType)['tax_amount'];
    }

    public function resolveTaxType(string $taxCode, ?CarbonInterface $date = null, string $direction = 'sales'): ?TaxType
    {
        $dateString = ($date ?? now())->toDateString();

        return TaxType::query()
            ->where('code', strtoupper(trim($taxCode)))
            ->where('status', 'active')
            ->whereDate('effective_from', '<=', $dateString)
            ->where(function ($query) use ($dateString) {
                $query->whereNull('effective_to')->orWhereDate('effective_to', '>=', $dateString);
            })
            ->where(function ($query) use ($direction) {
                $query->where('tax_direction', $direction)
                    ->orWhere('tax_direction', 'both');
            })
            ->first();
    }

    public function resolveDefaultTaxType(array $payload, string $direction = 'sales'): ?TaxType
    {
        if (! empty($payload['tax_type_id'])) {
            return TaxType::query()->find((int) $payload['tax_type_id']);
        }

        $settings = $this->financialSettings->get();
        $taxTypeId = $direction === 'purchase'
            ? ($settings->default_purchase_tax_type_id ?? $settings->default_tax_type_id)
            : ($settings->default_sales_tax_type_id ?? $settings->default_tax_type_id);

        return $taxTypeId ? TaxType::query()->find($taxTypeId) : null;
    }

    /**
     * @return array<int, array{tax_type_id: int, tax_type_code: string, tax_type_name: string, output_tax: float, input_tax: float, net_payable: float}>
     */
    public function getTaxReturn(FiscalYear $fiscalYear): array
    {
        return $this->summarize(
            $fiscalYear->start_date->toDateString(),
            $fiscalYear->end_date->toDateString(),
        )->all();
    }
}
