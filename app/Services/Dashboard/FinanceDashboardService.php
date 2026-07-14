<?php

declare(strict_types=1);

namespace App\Services\Dashboard;

use App\Enums\BankStatementLineStatus;
use App\Enums\JournalEntryStatus;
use App\Models\BankStatementLine;
use App\Models\JournalEntry;
use App\Services\Accounting\FinancialReportingService;

final class FinanceDashboardService
{
    public function __construct(
        private readonly FinancialReportingService $reports,
    ) {}

    /**
     * @return array{
     *     unposted_journals: int,
     *     bank_unmatched: int,
     *     ar_aging_total: float,
     *     ap_aging_total: float,
     * }
     */
    public function kpis(?int $branchId = null): array
    {
        $filters = array_filter(['branch_id' => $branchId], fn ($v) => $v !== null);

        $ar = $this->reports->arAging($filters);
        $ap = $this->reports->apAging($filters);

        return [
            'unposted_journals' => $this->unpostedJournalCount($branchId),
            'bank_unmatched' => $this->bankUnmatchedCount($branchId),
            'ar_aging_total' => round((float) ($ar['totals']['total'] ?? 0), 2),
            'ap_aging_total' => round((float) ($ap['totals']['total'] ?? 0), 2),
        ];
    }

    private function unpostedJournalCount(?int $branchId): int
    {
        return (int) JournalEntry::query()
            ->whereIn('status', [
                JournalEntryStatus::Draft,
                JournalEntryStatus::PendingApproval,
                JournalEntryStatus::Approved,
            ])
            ->when($branchId !== null, fn ($q) => $q->where('branch_id', $branchId))
            ->toBase()
            ->count('*');
    }

    private function bankUnmatchedCount(?int $branchId): int
    {
        return (int) BankStatementLine::query()
            ->whereIn('status', [
                BankStatementLineStatus::Unmatched,
                BankStatementLineStatus::Suggested,
                BankStatementLineStatus::PartiallyMatched,
            ])
            ->when(
                $branchId !== null,
                fn ($q) => $q->whereHas(
                    'bankAccount',
                    fn ($account) => $account->where('branch_id', $branchId),
                ),
            )
            ->toBase()
            ->count('*');
    }
}
