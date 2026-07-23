<?php

declare(strict_types=1);

namespace App\Services\Accounting;

use App\Enums\CostCentreAllocationMethod;
use App\Enums\JournalEntryStatus;
use App\Models\CostCentre;
use App\Models\CostCentreAllocation;
use App\Models\JournalEntry;
use App\Models\JournalTransaction;
use DomainException;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

final class CostCentreAllocationService
{
    public function __construct(
        private readonly JournalService $journalService,
    ) {}

    /**
     * @param  list<array{cost_centre_id: int, percent?: float|null}>  $targets
     * @return array{allocations: list<CostCentreAllocation>, journal: JournalEntry}
     */
    public function allocate(
        JournalTransaction $source,
        CostCentreAllocationMethod $method,
        array $targets,
        int $userId,
        ?string $periodFrom = null,
        ?string $periodTo = null,
    ): array {
        $source->loadMissing('journalEntry');

        if ($source->journalEntry?->status !== JournalEntryStatus::Posted) {
            throw new DomainException('Only posted journal lines can be allocated.');
        }

        if ($targets === []) {
            throw new DomainException('At least one target cost centre is required.');
        }

        $sourceAmount = $this->sourceAmount($source);
        if (bccomp((string) $sourceAmount, '0', 2) <= 0) {
            throw new DomainException('Source line amount must be greater than zero.');
        }

        $weights = $this->resolveWeights($method, $targets, $periodFrom, $periodTo);
        $splits = $this->splitAmount($sourceAmount, $weights);

        return DB::transaction(function () use ($source, $method, $splits, $userId, $sourceAmount) {
            CostCentreAllocation::query()
                ->where('source_journal_transaction_id', $source->id)
                ->delete();

            $allocations = [];
            foreach ($splits as $split) {
                $allocations[] = CostCentreAllocation::query()->create([
                    'source_journal_transaction_id' => $source->id,
                    'cost_centre_id' => $split['cost_centre_id'],
                    'allocation_method' => $method,
                    'allocation_percent' => $split['percent'],
                    'allocated_amount' => $split['amount'],
                ]);
            }

            $journal = $this->postReclassJournal($source, $splits, $sourceAmount, $userId);

            return [
                'allocations' => $allocations,
                'journal' => $journal,
            ];
        });
    }

    private function sourceAmount(JournalTransaction $source): float
    {
        $debit = (string) $source->debit;
        $credit = (string) $source->credit;

        if (bccomp($debit, '0', 2) > 0) {
            return (float) $debit;
        }

        return (float) $credit;
    }

    /**
     * @param  list<array{cost_centre_id: int, percent?: float|null}>  $targets
     * @return list<array{cost_centre_id: int, weight: string}>
     */
    private function resolveWeights(
        CostCentreAllocationMethod $method,
        array $targets,
        ?string $periodFrom,
        ?string $periodTo,
    ): array {
        $centreIds = array_values(array_unique(array_map(
            static fn (array $t): int => (int) $t['cost_centre_id'],
            $targets,
        )));

        $centres = CostCentre::query()->whereIn('id', $centreIds)->get()->keyBy('id');

        if ($centres->count() !== count($centreIds)) {
            throw new DomainException('One or more target cost centres were not found.');
        }

        return match ($method) {
            CostCentreAllocationMethod::Percentage,
            CostCentreAllocationMethod::Manual => $this->percentWeights($targets),
            CostCentreAllocationMethod::EqualSplit => array_map(
                static fn (int $id): array => ['cost_centre_id' => $id, 'weight' => '1'],
                $centreIds,
            ),
            CostCentreAllocationMethod::Headcount => $this->driverWeights($centres, 'headcount'),
            CostCentreAllocationMethod::FloorArea => $this->driverWeights($centres, 'floor_area'),
            CostCentreAllocationMethod::RevenueShare => $this->revenueWeights($centreIds, $periodFrom, $periodTo),
        };
    }

    /**
     * @param  list<array{cost_centre_id: int, percent?: float|null}>  $targets
     * @return list<array{cost_centre_id: int, weight: string}>
     */
    private function percentWeights(array $targets): array
    {
        $weights = [];
        $sum = '0';

        foreach ($targets as $target) {
            $percent = isset($target['percent']) ? (string) $target['percent'] : '0';
            if (bccomp($percent, '0', 4) <= 0) {
                throw new DomainException('Each percentage allocation target must have a positive percent.');
            }
            $weights[] = [
                'cost_centre_id' => (int) $target['cost_centre_id'],
                'weight' => $percent,
            ];
            $sum = bcadd($sum, $percent, 4);
        }

        if (bccomp($sum, '100', 4) !== 0) {
            throw new DomainException('Allocation percents must sum to 100.');
        }

        return $weights;
    }

    /**
     * @param  Collection<int, CostCentre>  $centres
     * @return list<array{cost_centre_id: int, weight: string}>
     */
    private function driverWeights($centres, string $column): array
    {
        $weights = [];

        foreach ($centres as $centre) {
            $value = (string) ($centre->{$column} ?? 0);
            if (bccomp($value, '0', 4) <= 0) {
                throw new DomainException("Cost centre {$centre->code} is missing a positive {$column} driver.");
            }
            $weights[] = [
                'cost_centre_id' => (int) $centre->id,
                'weight' => $value,
            ];
        }

        return $weights;
    }

    /**
     * @param  list<int>  $centreIds
     * @return list<array{cost_centre_id: int, weight: string}>
     */
    private function revenueWeights(array $centreIds, ?string $periodFrom, ?string $periodTo): array
    {
        if ($periodFrom === null || $periodTo === null) {
            throw new DomainException('Revenue share allocation requires a period_from and period_to.');
        }

        $weights = [];

        foreach ($centreIds as $centreId) {
            $revenue = (string) JournalTransaction::query()
                ->join('journal_entries', 'journal_entries.id', '=', 'journal_transactions.journal_entry_id')
                ->join('chart_of_accounts', 'chart_of_accounts.id', '=', 'journal_transactions.account_id')
                ->where('journal_entries.status', JournalEntryStatus::Posted)
                ->where('journal_transactions.cost_centre_id', $centreId)
                ->where('chart_of_accounts.type', 'revenue')
                ->whereDate('journal_entries.journal_date', '>=', $periodFrom)
                ->whereDate('journal_entries.journal_date', '<=', $periodTo)
                ->selectRaw('COALESCE(SUM(journal_transactions.credit - journal_transactions.debit), 0) as revenue')
                ->value('revenue');

            if (bccomp($revenue, '0', 2) <= 0) {
                throw new DomainException("Cost centre #{$centreId} has no revenue in the selected period.");
            }

            $weights[] = [
                'cost_centre_id' => $centreId,
                'weight' => $revenue,
            ];
        }

        return $weights;
    }

    /**
     * @param  list<array{cost_centre_id: int, weight: string}>  $weights
     * @return list<array{cost_centre_id: int, percent: string, amount: string}>
     */
    private function splitAmount(float $sourceAmount, array $weights): array
    {
        $totalWeight = '0';
        foreach ($weights as $weight) {
            $totalWeight = bcadd($totalWeight, $weight['weight'], 8);
        }

        if (bccomp($totalWeight, '0', 8) <= 0) {
            throw new DomainException('Allocation weights must be greater than zero.');
        }

        $amountStr = number_format($sourceAmount, 2, '.', '');
        $allocated = '0.00';
        $splits = [];
        $lastIndex = count($weights) - 1;

        foreach ($weights as $index => $weight) {
            if ($index === $lastIndex) {
                $amount = bcsub($amountStr, $allocated, 2);
            } else {
                $share = bcmul($amountStr, bcdiv($weight['weight'], $totalWeight, 12), 8);
                $amount = bcadd($share, '0', 2);
                $allocated = bcadd($allocated, $amount, 2);
            }

            $percent = bcmul(bcdiv($weight['weight'], $totalWeight, 12), '100', 4);

            $splits[] = [
                'cost_centre_id' => $weight['cost_centre_id'],
                'percent' => $percent,
                'amount' => $amount,
            ];
        }

        return $splits;
    }

    /**
     * @param  list<array{cost_centre_id: int, percent: string, amount: string}>  $splits
     */
    private function postReclassJournal(
        JournalTransaction $source,
        array $splits,
        float $sourceAmount,
        int $userId,
    ): JournalEntry {
        $isDebitSource = bccomp((string) $source->debit, '0', 2) > 0;
        $amountStr = number_format($sourceAmount, 2, '.', '');

        $lines = [];

        foreach ($splits as $split) {
            $lines[] = [
                'account_id' => $source->account_id,
                'debit' => $isDebitSource ? (float) $split['amount'] : 0,
                'credit' => $isDebitSource ? 0 : (float) $split['amount'],
                'cost_centre_id' => $split['cost_centre_id'],
                'branch_id' => $source->branch_id,
                'description' => __('Cost centre allocation from line #:id', ['id' => $source->id]),
            ];
        }

        $lines[] = [
            'account_id' => $source->account_id,
            'debit' => $isDebitSource ? 0 : (float) $amountStr,
            'credit' => $isDebitSource ? (float) $amountStr : 0,
            'cost_centre_id' => $source->cost_centre_id,
            'branch_id' => $source->branch_id,
            'description' => __('Cost centre allocation clear source line #:id', ['id' => $source->id]),
        ];

        $entry = $this->journalService->createDraft([
            'journal_date' => $source->journalEntry?->journal_date?->toDateString() ?? now()->toDateString(),
            'branch_id' => $source->journalEntry?->branch_id ?? $source->branch_id,
            'legal_entity_id' => $source->journalEntry?->legal_entity_id,
            'description' => __('Cost centre allocation — source JT #:id', ['id' => $source->id]),
            'source_module' => 'accounting',
            'source_event' => 'cost_centre.allocated',
            'source_reference_type' => JournalTransaction::class,
            'source_reference_id' => $source->id,
            'is_system_generated' => true,
        ], $lines, $userId);

        return $this->journalService->post($entry, $userId);
    }
}
