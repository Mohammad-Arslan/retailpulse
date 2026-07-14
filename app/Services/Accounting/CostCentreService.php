<?php

declare(strict_types=1);

namespace App\Services\Accounting;

use App\DTOs\Accounting\CreateCostCentreData;
use App\DTOs\Accounting\UpdateCostCentreData;
use App\Enums\JournalEntryStatus;
use App\Models\CostCentre;
use App\Models\JournalTransaction;
use App\Models\OrganizationEntity;
use App\Repositories\Contracts\BranchRepositoryInterface;
use App\Repositories\Contracts\CostCentreRepositoryInterface;
use App\Support\CostCentrePresenter;
use DomainException;

final class CostCentreService
{
    public function __construct(
        private readonly CostCentreRepositoryInterface $costCentreRepository,
        private readonly BranchRepositoryInterface $branchRepository,
    ) {}

    /**
     * @return array{
     *     costCentres: list<array<string, mixed>>,
     *     parentOptions: list<array{id: int, code: string, name: string}>,
     *     branches: mixed,
     *     legalEntities: mixed,
     *     allocatableLines: list<array<string, mixed>>,
     *     allocationMethods: list<string>
     * }
     */
    public function indexPayload(): array
    {
        $centres = $this->costCentreRepository->allOrderedWithRelations();

        $allocatableLines = JournalTransaction::query()
            ->join('journal_entries', 'journal_entries.id', '=', 'journal_transactions.journal_entry_id')
            ->join('chart_of_accounts', 'chart_of_accounts.id', '=', 'journal_transactions.account_id')
            ->where('journal_entries.status', JournalEntryStatus::Posted)
            ->where('chart_of_accounts.type', 'expense')
            ->orderByDesc('journal_entries.journal_date')
            ->orderByDesc('journal_transactions.id')
            ->limit(50)
            ->get([
                'journal_transactions.id',
                'journal_transactions.debit',
                'journal_transactions.credit',
                'journal_transactions.cost_centre_id',
                'journal_entries.journal_number',
                'journal_entries.journal_date',
                'chart_of_accounts.code as account_code',
                'chart_of_accounts.name as account_name',
            ])
            ->map(static fn ($row) => [
                'id' => $row->id,
                'label' => sprintf(
                    '%s · %s · %s — %s',
                    $row->journal_number,
                    $row->journal_date,
                    $row->account_code,
                    number_format((float) $row->debit > 0 ? (float) $row->debit : (float) $row->credit, 2),
                ),
            ])
            ->values()
            ->all();

        return [
            'costCentres' => CostCentrePresenter::tree($centres),
            'parentOptions' => CostCentrePresenter::parentOptions($centres),
            'branches' => $this->branchRepository->allActive()->map->only(['id', 'name'])->values(),
            'legalEntities' => OrganizationEntity::query()->orderBy('legal_name')->get(['id', 'legal_name']),
            'allocatableLines' => $allocatableLines,
            'allocationMethods' => \App\Enums\CostCentreAllocationMethod::values(),
        ];
    }

    public function create(CreateCostCentreData $data): CostCentre
    {
        return $this->costCentreRepository->create($data->toArray());
    }

    public function update(CostCentre $costCentre, UpdateCostCentreData $data): CostCentre
    {
        return $this->costCentreRepository->update($costCentre, $data->attributes);
    }

    public function delete(CostCentre $costCentre): void
    {
        if ($this->costCentreRepository->hasChildren($costCentre)) {
            throw new DomainException(__('Cannot delete a cost centre with children.'));
        }

        $this->costCentreRepository->delete($costCentre);
    }
}
