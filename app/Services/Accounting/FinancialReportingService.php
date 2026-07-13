<?php

declare(strict_types=1);

namespace App\Services\Accounting;

use App\Enums\ChartOfAccountType;
use App\Enums\JournalEntryStatus;
use App\Models\AccountMapping;
use App\Models\AuditLog;
use App\Models\ChartOfAccount;
use App\Models\FinancialSetting;
use App\Models\InventoryCostLayer;
use App\Models\JournalEntry;
use App\Models\JournalTransaction;
use App\Models\Sale;
use App\Models\SupplierInvoice;
use App\Support\AccountingAuditPresenter;
use App\Support\AccountingAuditTypes;
use Carbon\Carbon;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

final class FinancialReportingService
{
    /**
     * @param  array<string, mixed>  $filters
     * @return array{rows: list<array<string, mixed>>, totals: array<string, float>}
     */
    public function trialBalance(array $filters = []): array
    {
        $dateFrom = $this->dateFrom($filters);
        $dateTo = $this->dateTo($filters);

        $accounts = ChartOfAccount::query()
            ->where('is_postable', true)
            ->where('status', 'active')
            ->orderBy('code')
            ->get();

        $rows = [];
        $totals = ['opening_debit' => 0.0, 'opening_credit' => 0.0, 'period_debit' => 0.0, 'period_credit' => 0.0, 'closing_debit' => 0.0, 'closing_credit' => 0.0];

        foreach ($accounts as $account) {
            $opening = $this->accountBalance($account->id, null, Carbon::parse($dateFrom)->subDay()->toDateString(), $filters);
            $periodGross = $this->accountGrossMovement($account->id, $dateFrom, $dateTo, $filters);
            $period = $periodGross['debit'] - $periodGross['credit'];
            $closing = $opening + $period;

            if ($this->isZeroBalanceRow($opening, $period, $closing)) {
                continue;
            }

            $row = $this->formatBalanceRow(
                $account,
                $opening,
                $periodGross['debit'],
                $periodGross['credit'],
                $closing,
            );
            $rows[] = $row;

            foreach (['opening_debit', 'opening_credit', 'period_debit', 'period_credit', 'closing_debit', 'closing_credit'] as $key) {
                $totals[$key] += (float) ($row[$key] ?? 0);
            }
        }

        return ['rows' => $rows, 'totals' => $totals, 'filters' => $this->normalizeFilters($filters, $dateFrom, $dateTo)];
    }

    /**
     * @param  array<string, mixed>  $filters
     * @return array{rows: list<array<string, mixed>>, totals: array<string, float>}
     */
    public function profitAndLoss(array $filters = []): array
    {
        $dateFrom = $this->dateFrom($filters);
        $dateTo = $this->dateTo($filters);

        $accounts = ChartOfAccount::query()
            ->whereIn('type', [ChartOfAccountType::Revenue, ChartOfAccountType::Expense])
            ->where('is_postable', true)
            ->where('status', 'active')
            ->orderBy('type')
            ->orderBy('code')
            ->get();

        $rows = [];
        $totalRevenue = 0.0;
        $totalExpense = 0.0;

        foreach ($accounts as $account) {
            $movement = $this->accountMovement($account->id, $dateFrom, $dateTo, $filters);

            if (abs($movement) < 0.005) {
                continue;
            }

            $amount = $account->type === ChartOfAccountType::Revenue ? -$movement : $movement;
            $rows[] = [
                'account_id' => $account->id,
                'account_code' => $account->code,
                'account_name' => $account->name,
                'type' => $account->type->value,
                'amount' => round($amount, 2),
            ];

            if ($account->type === ChartOfAccountType::Revenue) {
                $totalRevenue += $amount;
            } else {
                $totalExpense += $amount;
            }
        }

        return [
            'rows' => $rows,
            'totals' => [
                'revenue' => round($totalRevenue, 2),
                'expense' => round($totalExpense, 2),
                'net_income' => round($totalRevenue - $totalExpense, 2),
            ],
            'filters' => $this->normalizeFilters($filters, $dateFrom, $dateTo),
        ];
    }

    /**
     * @param  array<string, mixed>  $filters
     * @return array{rows: list<array<string, mixed>>, totals: array<string, float>}
     */
    public function balanceSheet(array $filters = []): array
    {
        $asOf = $this->dateTo($filters);

        $accounts = ChartOfAccount::query()
            ->whereIn('type', [ChartOfAccountType::Asset, ChartOfAccountType::Liability, ChartOfAccountType::Equity])
            ->where('is_postable', true)
            ->where('status', 'active')
            ->orderBy('type')
            ->orderBy('code')
            ->get();

        $rows = [];
        $totals = ['asset' => 0.0, 'liability' => 0.0, 'equity' => 0.0];

        foreach ($accounts as $account) {
            $balance = $this->accountBalance($account->id, null, $asOf, $filters);

            if (abs($balance) < 0.005) {
                continue;
            }

            $displayBalance = in_array($account->type, [ChartOfAccountType::Liability, ChartOfAccountType::Equity], true)
                ? -$balance
                : $balance;

            $rows[] = [
                'account_id' => $account->id,
                'account_code' => $account->code,
                'account_name' => $account->name,
                'type' => $account->type->value,
                'balance' => round($displayBalance, 2),
            ];

            $totals[$account->type->value] += $displayBalance;
        }

        foreach ($totals as $key => $value) {
            $totals[$key] = round($value, 2);
        }

        return ['rows' => $rows, 'totals' => $totals, 'filters' => $this->normalizeFilters($filters, null, $asOf)];
    }

    /**
     * @param  array<string, mixed>  $filters
     * @return array{rows: list<array<string, mixed>>}
     */
    public function generalLedger(array $filters = []): array
    {
        $dateFrom = $this->dateFrom($filters);
        $dateTo = $this->dateTo($filters);
        $accountId = isset($filters['account_id']) ? (int) $filters['account_id'] : null;

        $query = $this->postedLinesQuery($filters)
            ->whereDate('journal_entries.journal_date', '>=', $dateFrom)
            ->whereDate('journal_entries.journal_date', '<=', $dateTo)
            ->with(['account:id,code,name', 'journalEntry:id,journal_number,journal_date,description,status']);

        if ($accountId) {
            $query->where('journal_transactions.account_id', $accountId);
        } elseif (! empty($filters['account_ids']) && is_array($filters['account_ids'])) {
            $query->whereIn('journal_transactions.account_id', $filters['account_ids']);
        }

        $lines = $query
            ->orderBy('journal_entries.journal_date')
            ->orderBy('journal_entries.journal_number')
            ->orderBy('journal_transactions.line_sequence')
            ->get();

        $runningBalances = [];
        $rows = [];

        foreach ($lines as $line) {
            $acctId = $line->account_id;
            $runningBalances[$acctId] = ($runningBalances[$acctId] ?? $this->accountBalance($acctId, null, Carbon::parse($dateFrom)->subDay()->toDateString(), $filters))
                + (float) $line->debit - (float) $line->credit;

            $rows[] = [
                'id' => $line->id,
                'journal_entry_id' => $line->journal_entry_id,
                'journal_number' => $line->journalEntry?->journal_number,
                'journal_date' => $line->journalEntry?->journal_date?->toDateString(),
                'account_id' => $acctId,
                'account_code' => $line->account?->code,
                'account_name' => $line->account?->name,
                'description' => $line->description ?? $line->journalEntry?->description,
                'debit' => round((float) $line->debit, 2),
                'credit' => round((float) $line->credit, 2),
                'running_balance' => round($runningBalances[$acctId], 2),
            ];
        }

        return ['rows' => $rows, 'filters' => $this->normalizeFilters($filters, $dateFrom, $dateTo)];
    }

    /**
     * @param  array<string, mixed>  $filters
     * @return array{rows: list<array<string, mixed>>, totals: array<string, float>}
     */
    public function costCentreProfitAndLoss(array $filters = []): array
    {
        $dateFrom = $this->dateFrom($filters);
        $dateTo = $this->dateTo($filters);
        $costCentreId = isset($filters['cost_centre_id']) ? (int) $filters['cost_centre_id'] : null;

        $query = $this->postedLinesQuery($filters)
            ->join('chart_of_accounts', 'chart_of_accounts.id', '=', 'journal_transactions.account_id')
            ->whereIn('chart_of_accounts.type', [ChartOfAccountType::Revenue->value, ChartOfAccountType::Expense->value])
            ->whereDate('journal_entries.journal_date', '>=', $dateFrom)
            ->whereDate('journal_entries.journal_date', '<=', $dateTo)
            ->select([
                'journal_transactions.cost_centre_id',
                'chart_of_accounts.type',
                DB::raw('SUM(journal_transactions.debit) as total_debit'),
                DB::raw('SUM(journal_transactions.credit) as total_credit'),
            ])
            ->groupBy('journal_transactions.cost_centre_id', 'chart_of_accounts.type');

        if ($costCentreId) {
            $query->where('journal_transactions.cost_centre_id', $costCentreId);
        } else {
            $query->whereNotNull('journal_transactions.cost_centre_id');
        }

        $aggregates = $query->get();
        $byCentre = [];

        foreach ($aggregates as $row) {
            $centreId = (int) $row->cost_centre_id;
            $byCentre[$centreId] ??= ['revenue' => 0.0, 'expense' => 0.0];
            $movement = (float) $row->total_debit - (float) $row->total_credit;

            if ($row->type === ChartOfAccountType::Revenue->value) {
                $byCentre[$centreId]['revenue'] += -$movement;
            } else {
                $byCentre[$centreId]['expense'] += $movement;
            }
        }

        $centreNames = DB::table('cost_centres')->whereIn('id', array_keys($byCentre))->pluck('name', 'id');
        $centreCodes = DB::table('cost_centres')->whereIn('id', array_keys($byCentre))->pluck('code', 'id');

        $rows = [];
        $totalRevenue = 0.0;
        $totalExpense = 0.0;

        foreach ($byCentre as $centreId => $amounts) {
            $revenue = round($amounts['revenue'], 2);
            $expense = round($amounts['expense'], 2);
            $rows[] = [
                'cost_centre_id' => $centreId,
                'cost_centre_code' => $centreCodes[$centreId] ?? '',
                'cost_centre_name' => $centreNames[$centreId] ?? '',
                'revenue' => $revenue,
                'expense' => $expense,
                'net_income' => round($revenue - $expense, 2),
            ];
            $totalRevenue += $revenue;
            $totalExpense += $expense;
        }

        usort($rows, fn (array $a, array $b) => strcmp($a['cost_centre_code'], $b['cost_centre_code']));

        return [
            'rows' => $rows,
            'totals' => [
                'revenue' => round($totalRevenue, 2),
                'expense' => round($totalExpense, 2),
                'net_income' => round($totalRevenue - $totalExpense, 2),
            ],
            'filters' => $this->normalizeFilters($filters, $dateFrom, $dateTo),
        ];
    }

    /**
     * @param  array<string, mixed>  $filters
     * @return array{rows: list<array<string, mixed>>, totals: array<string, float>}
     */
    public function cashFlow(array $filters = []): array
    {
        $dateFrom = $this->dateFrom($filters);
        $dateTo = $this->dateTo($filters);

        $cashAccountIds = $this->resolveCashAccountIds($filters);
        $rows = ['operating' => 0.0, 'investing' => 0.0, 'financing' => 0.0];
        $detail = [];

        if ($cashAccountIds->isEmpty()) {
            return [
                'rows' => [],
                'totals' => $rows,
                'filters' => $this->normalizeFilters($filters, $dateFrom, $dateTo),
            ];
        }

        $lines = $this->postedLinesQuery($filters)
            ->whereIn('journal_transactions.account_id', $cashAccountIds)
            ->whereDate('journal_entries.journal_date', '>=', $dateFrom)
            ->whereDate('journal_entries.journal_date', '<=', $dateTo)
            ->with(['account:id,code,name,type', 'journalEntry:id,journal_number,journal_date'])
            ->get();

        foreach ($lines as $line) {
            $net = (float) $line->debit - (float) $line->credit;
            $category = $this->classifyCashFlowCategory($line->account?->type?->value);
            $rows[$category] += $net;
            $detail[] = [
                'journal_entry_id' => $line->journal_entry_id,
                'journal_number' => $line->journalEntry?->journal_number,
                'journal_date' => $line->journalEntry?->journal_date?->toDateString(),
                'account_code' => $line->account?->code,
                'category' => $category,
                'amount' => round($net, 2),
            ];
        }

        foreach ($rows as $key => $value) {
            $rows[$key] = round($value, 2);
        }

        return [
            'rows' => $detail,
            'totals' => [
                'operating' => $rows['operating'],
                'investing' => $rows['investing'],
                'financing' => $rows['financing'],
                'net_change' => round($rows['operating'] + $rows['investing'] + $rows['financing'], 2),
            ],
            'filters' => $this->normalizeFilters($filters, $dateFrom, $dateTo),
        ];
    }

    /**
     * @param  array<string, mixed>  $filters
     * @return array{rows: list<array<string, mixed>>, totals: array<string, float>}
     */
    public function arAging(array $filters = []): array
    {
        $asOf = $this->dateTo($filters);
        $branchId = isset($filters['branch_id']) ? (int) $filters['branch_id'] : null;

        $query = Sale::query()
            ->with(['customer:id,name'])
            ->where('balance_due', '>', 0)
            ->whereDate('completed_at', '<=', $asOf);

        if ($branchId) {
            $query->where('branch_id', $branchId);
        }

        $sales = $query->get();
        $rows = [];
        $totals = ['current' => 0.0, 'bucket_30' => 0.0, 'bucket_60' => 0.0, 'bucket_90' => 0.0, 'bucket_over_90' => 0.0, 'total' => 0.0];
        $asOfDate = Carbon::parse($asOf);

        foreach ($sales->groupBy('customer_id') as $customerId => $customerSales) {
            $buckets = ['current' => 0.0, 'bucket_30' => 0.0, 'bucket_60' => 0.0, 'bucket_90' => 0.0, 'bucket_over_90' => 0.0];

            foreach ($customerSales as $sale) {
                $dueDate = $sale->completed_at ?? $sale->created_at;
                $days = $dueDate ? $asOfDate->diffInDays($dueDate) : 0;
                $amount = (float) $sale->balance_due;
                $key = match (true) {
                    $days <= 30 => 'current',
                    $days <= 60 => 'bucket_30',
                    $days <= 90 => 'bucket_60',
                    $days <= 120 => 'bucket_90',
                    default => 'bucket_over_90',
                };
                $buckets[$key] += $amount;
            }

            $total = array_sum($buckets);
            if ($total < 0.005) {
                continue;
            }

            $customer = $customerSales->first()->customer;
            $rows[] = [
                'party_id' => $customerId,
                'party_name' => $customer?->name ?? 'Unknown',
                ...array_map(fn (float $v) => round($v, 2), $buckets),
                'total' => round($total, 2),
            ];

            foreach ($buckets as $key => $value) {
                $totals[$key] += $value;
            }
            $totals['total'] += $total;
        }

        foreach ($totals as $key => $value) {
            $totals[$key] = round($value, 2);
        }

        return ['rows' => $rows, 'totals' => $totals, 'filters' => $this->normalizeFilters($filters, null, $asOf)];
    }

    /**
     * @param  array<string, mixed>  $filters
     * @return array{rows: list<array<string, mixed>>, totals: array<string, float>}
     */
    public function apAging(array $filters = []): array
    {
        $asOf = $this->dateTo($filters);
        $branchId = isset($filters['branch_id']) ? (int) $filters['branch_id'] : null;

        $query = SupplierInvoice::query()
            ->with(['supplier:id,name', 'payments'])
            ->whereDate('invoice_date', '<=', $asOf);

        if ($branchId) {
            $query->where('branch_id', $branchId);
        }

        $invoices = $query->get();
        $rows = [];
        $totals = ['current' => 0.0, 'bucket_30' => 0.0, 'bucket_60' => 0.0, 'bucket_90' => 0.0, 'bucket_over_90' => 0.0, 'total' => 0.0];
        $asOfDate = Carbon::parse($asOf);
        $bySupplier = [];

        foreach ($invoices as $invoice) {
            $paid = (float) $invoice->payments->sum('amount');
            $outstanding = (float) $invoice->total - $paid;

            if ($outstanding < 0.005) {
                continue;
            }

            $dueDate = $invoice->effectiveDueDate() ?? $invoice->invoice_date;
            $days = $dueDate ? $asOfDate->diffInDays($dueDate) : 0;
            $key = match (true) {
                $days <= 30 => 'current',
                $days <= 60 => 'bucket_30',
                $days <= 90 => 'bucket_60',
                $days <= 120 => 'bucket_90',
                default => 'bucket_over_90',
            };

            $supplierId = (int) $invoice->supplier_id;
            $bySupplier[$supplierId] ??= [
                'party_name' => $invoice->supplier?->name ?? 'Unknown',
                'current' => 0.0,
                'bucket_30' => 0.0,
                'bucket_60' => 0.0,
                'bucket_90' => 0.0,
                'bucket_over_90' => 0.0,
            ];
            $bySupplier[$supplierId][$key] += $outstanding;
        }

        foreach ($bySupplier as $supplierId => $buckets) {
            $partyName = $buckets['party_name'];
            unset($buckets['party_name']);
            $sum = array_sum($buckets);

            $rows[] = [
                'party_id' => $supplierId,
                'party_name' => $partyName,
                ...array_map(fn (float $v) => round($v, 2), $buckets),
                'total' => round($sum, 2),
            ];

            foreach ($buckets as $bucketKey => $value) {
                $totals[$bucketKey] += $value;
            }
            $totals['total'] += $sum;
        }

        foreach ($totals as $key => $value) {
            $totals[$key] = round($value, 2);
        }

        return ['rows' => $rows, 'totals' => $totals, 'filters' => $this->normalizeFilters($filters, null, $asOf)];
    }

    /**
     * @param  array<string, mixed>  $filters
     */
    public function bankBook(array $filters = []): array
    {
        $bankAccountIds = $this->resolveBankAccountIds();

        return $this->generalLedger([
            ...$filters,
            'account_ids' => $bankAccountIds->all(),
        ]);
    }

    /**
     * @param  array<string, mixed>  $filters
     */
    public function inventoryValuation(array $filters = []): array
    {
        if (! Schema::hasTable('inventory_cost_layers')) {
            return ['rows' => [], 'totals' => ['total_value' => 0.0], 'filters' => $this->normalizeFilters($filters, null, $this->dateTo($filters))];
        }

        $warehouseId = isset($filters['warehouse_id']) ? (int) $filters['warehouse_id'] : null;

        $query = InventoryCostLayer::query()
            ->with(['variant:id,sku,name', 'warehouse:id,name'])
            ->where('qty_remaining', '>', 0)
            ->where('status', 'active');

        if ($warehouseId) {
            $query->where('warehouse_id', $warehouseId);
        }

        $layers = $query->get();
        $rows = [];
        $totalValue = 0.0;

        foreach ($layers as $layer) {
            $value = (float) $layer->qty_remaining * (float) $layer->unit_cost;
            $totalValue += $value;
            $rows[] = [
                'variant_id' => $layer->product_variant_id,
                'sku' => $layer->variant?->sku,
                'product_name' => $layer->variant?->name,
                'warehouse' => $layer->warehouse?->name,
                'qty_remaining' => round((float) $layer->qty_remaining, 4),
                'unit_cost' => round((float) $layer->unit_cost, 4),
                'total_value' => round($value, 2),
            ];
        }

        return [
            'rows' => $rows,
            'totals' => ['total_value' => round($totalValue, 2)],
            'filters' => $this->normalizeFilters($filters, null, $this->dateTo($filters)),
        ];
    }

    /**
     * @param  array<string, mixed>  $filters
     */
    public function assetRegister(array $filters = []): array
    {
        if (! Schema::hasTable('fixed_assets')) {
            return ['rows' => [], 'totals' => ['net_book_value' => 0.0], 'filters' => $this->normalizeFilters($filters, null, $this->dateTo($filters))];
        }

        return ['rows' => [], 'totals' => ['net_book_value' => 0.0], 'filters' => $this->normalizeFilters($filters, null, $this->dateTo($filters))];
    }

    /**
     * @param  array<string, mixed>  $filters
     */
    public function fxRevaluation(array $filters = []): array
    {
        $dateFrom = $this->dateFrom($filters);
        $dateTo = $this->dateTo($filters);

        $lines = $this->postedLinesQuery($filters)
            ->where('journal_transactions.currency_code', '!=', $this->settings()->functional_currency_code)
            ->whereDate('journal_entries.journal_date', '>=', $dateFrom)
            ->whereDate('journal_entries.journal_date', '<=', $dateTo)
            ->with(['account:id,code,name', 'journalEntry:id,journal_number,journal_date,source_event'])
            ->get();

        $rows = $lines->map(fn (JournalTransaction $line) => [
            'journal_entry_id' => $line->journal_entry_id,
            'journal_number' => $line->journalEntry?->journal_number,
            'journal_date' => $line->journalEntry?->journal_date?->toDateString(),
            'account_code' => $line->account?->code,
            'currency_code' => $line->currency_code,
            'transaction_amount' => round((float) ($line->transaction_currency_amount ?? 0), 2),
            'functional_amount' => round((float) $line->functional_currency_amount, 2),
            'exchange_rate' => $line->exchange_rate,
            'source_event' => $line->journalEntry?->source_event,
        ])->all();

        return ['rows' => $rows, 'filters' => $this->normalizeFilters($filters, $dateFrom, $dateTo)];
    }

    /**
     * @param  array<string, mixed>  $filters
     */
    public function pettyCash(array $filters = []): array
    {
        if (! Schema::hasTable('petty_cash_registers')) {
            return ['rows' => [], 'totals' => ['balance' => 0.0], 'filters' => $this->normalizeFilters($filters, null, $this->dateTo($filters))];
        }

        return ['rows' => [], 'totals' => ['balance' => 0.0], 'filters' => $this->normalizeFilters($filters, null, $this->dateTo($filters))];
    }

    /**
     * @param  array<string, mixed>  $filters
     */
    public function chequeStatus(array $filters = []): array
    {
        if (! Schema::hasTable('cheques')) {
            return ['rows' => [], 'filters' => $this->normalizeFilters($filters, null, $this->dateTo($filters))];
        }

        return ['rows' => [], 'filters' => $this->normalizeFilters($filters, null, $this->dateTo($filters))];
    }

    /**
     * @param  array<string, mixed>  $filters
     */
    public function auditTrail(array $filters = []): array
    {
        $dateFrom = $this->dateFrom($filters);
        $dateTo = $this->dateTo($filters);

        $query = AuditLog::query()
            ->with('user:id,name,email')
            ->whereIn('auditable_type', AccountingAuditTypes::classNames())
            ->whereDate('created_at', '>=', $dateFrom)
            ->whereDate('created_at', '<=', $dateTo)
            ->orderByDesc('created_at');

        if (! empty($filters['auditable_type'])) {
            $query->where('auditable_type', $filters['auditable_type']);
        }

        if (! empty($filters['event'])) {
            $query->where('event', $filters['event']);
        }

        $logs = $query->limit(500)->get();
        $entityLabels = AccountingAuditPresenter::entityLabels($logs);

        $rows = $logs->map(function (AuditLog $log) use ($entityLabels) {
            $type = (string) $log->auditable_type;
            $id = (int) $log->auditable_id;
            $entityLabel = $entityLabels[$type][$id] ?? ($id ? '#'.$id : '—');
            $journalEntryId = $type === JournalEntry::class ? $id : null;

            return [
                'id' => $log->id,
                'occurred_at' => $log->created_at?->toDateTimeString(),
                'event' => $log->event,
                'entity_type' => AccountingAuditTypes::shortName($type),
                'entity_label' => $entityLabel,
                'journal_entry_id' => $journalEntryId,
                'user_name' => $log->user?->name ?? 'System',
                'changes_summary' => AccountingAuditPresenter::changesSummary(
                    $log->event,
                    $log->old_values,
                    $log->new_values,
                ),
            ];
        })->all();

        return ['rows' => $rows, 'filters' => $this->normalizeFilters($filters, $dateFrom, $dateTo)];
    }

    /**
     * @param  array<string, mixed>  $filters
     */
    public function unpostedJournals(array $filters = []): array
    {
        $dateFrom = $this->dateFrom($filters);
        $dateTo = $this->dateTo($filters);

        $entries = JournalEntry::query()
            ->with(['branch:id,name'])
            ->whereIn('status', [JournalEntryStatus::Draft, JournalEntryStatus::PendingApproval, JournalEntryStatus::Approved])
            ->whereDate('journal_date', '>=', $dateFrom)
            ->whereDate('journal_date', '<=', $dateTo)
            ->when(isset($filters['branch_id']), fn (Builder $q) => $q->where('branch_id', (int) $filters['branch_id']))
            ->orderBy('journal_date')
            ->get();

        $rows = $entries->map(fn (JournalEntry $entry) => [
            'id' => $entry->id,
            'journal_number' => $entry->journal_number,
            'journal_date' => $entry->journal_date?->toDateString(),
            'status' => $entry->status->value,
            'description' => $entry->description,
            'branch_name' => $entry->branch?->name,
        ])->all();

        return ['rows' => $rows, 'filters' => $this->normalizeFilters($filters, $dateFrom, $dateTo)];
    }

    /**
     * @param  array<string, mixed>  $filters
     */
    public function journalRegister(array $filters = []): array
    {
        $dateFrom = $this->dateFrom($filters);
        $dateTo = $this->dateTo($filters);

        $entries = JournalEntry::query()
            ->with(['branch:id,name'])
            ->where('status', JournalEntryStatus::Posted)
            ->whereDate('journal_date', '>=', $dateFrom)
            ->whereDate('journal_date', '<=', $dateTo)
            ->when(isset($filters['branch_id']), fn (Builder $q) => $q->where('branch_id', (int) $filters['branch_id']))
            ->orderBy('journal_date')
            ->orderBy('journal_number')
            ->get();

        $rows = $entries->map(fn (JournalEntry $entry) => [
            'id' => $entry->id,
            'journal_number' => $entry->journal_number,
            'journal_date' => $entry->journal_date?->toDateString(),
            'description' => $entry->description,
            'branch_name' => $entry->branch?->name,
            'posted_at' => $entry->posted_at?->toIso8601String(),
        ])->all();

        return ['rows' => $rows, 'filters' => $this->normalizeFilters($filters, $dateFrom, $dateTo)];
    }

    /**
     * @param  array<string, mixed>  $report
     * @return list<list<string|int|float|null>>
     */
    public function toCsvRows(string $reportKey, array $report): array
    {
        $rows = $report['rows'] ?? [];

        if ($rows === []) {
            return [];
        }

        $headers = array_keys($rows[0]);

        return [
            $headers,
            ...array_map(fn (array $row) => array_values($row), $rows),
        ];
    }

    private function settings(): FinancialSetting
    {
        return app(FinancialSettingsService::class)->get();
    }

    /**
     * @param  array<string, mixed>  $filters
     */
    private function postedLinesQuery(array $filters): Builder
    {
        $query = JournalTransaction::query()
            ->join('journal_entries', 'journal_entries.id', '=', 'journal_transactions.journal_entry_id')
            ->where('journal_entries.status', JournalEntryStatus::Posted)
            ->select('journal_transactions.*');

        if (! empty($filters['branch_id'])) {
            $query->where('journal_entries.branch_id', (int) $filters['branch_id']);
        }

        if (! empty($filters['legal_entity_id'])) {
            $query->where('journal_entries.legal_entity_id', (int) $filters['legal_entity_id']);
        }

        if (! empty($filters['fiscal_year_id'])) {
            $query->where('journal_entries.fiscal_year_id', (int) $filters['fiscal_year_id']);
        }

        if (! empty($filters['cost_centre_id']) && Schema::hasColumn('journal_transactions', 'cost_centre_id')) {
            $query->where('journal_transactions.cost_centre_id', (int) $filters['cost_centre_id']);
        }

        if (! empty($filters['account_ids']) && is_array($filters['account_ids'])) {
            $query->whereIn('journal_transactions.account_id', $filters['account_ids']);
        }

        return $query;
    }

    /**
     * @param  array<string, mixed>  $filters
     */
    private function accountBalance(int $accountId, ?string $dateFrom, ?string $dateTo, array $filters): float
    {
        $query = $this->postedLinesQuery($filters)
            ->where('journal_transactions.account_id', $accountId);

        if ($dateFrom) {
            $query->whereDate('journal_entries.journal_date', '>=', $dateFrom);
        }

        if ($dateTo) {
            $query->whereDate('journal_entries.journal_date', '<=', $dateTo);
        }

        $sums = $query
            ->select([
                DB::raw('COALESCE(SUM(journal_transactions.debit), 0) as total_debit'),
                DB::raw('COALESCE(SUM(journal_transactions.credit), 0) as total_credit'),
            ])
            ->first();

        return (float) ($sums->total_debit ?? 0) - (float) ($sums->total_credit ?? 0);
    }

    /**
     * @param  array<string, mixed>  $filters
     */
    private function accountMovement(int $accountId, string $dateFrom, string $dateTo, array $filters): float
    {
        return $this->accountBalance($accountId, $dateFrom, $dateTo, $filters);
    }

    /**
     * @param  array<string, mixed>  $filters
     * @return array{debit: float, credit: float}
     */
    private function accountGrossMovement(int $accountId, string $dateFrom, string $dateTo, array $filters): array
    {
        $query = $this->postedLinesQuery($filters)
            ->where('journal_transactions.account_id', $accountId)
            ->whereDate('journal_entries.journal_date', '>=', $dateFrom)
            ->whereDate('journal_entries.journal_date', '<=', $dateTo);

        $sums = $query
            ->select([
                DB::raw('COALESCE(SUM(journal_transactions.debit), 0) as total_debit'),
                DB::raw('COALESCE(SUM(journal_transactions.credit), 0) as total_credit'),
            ])
            ->first();

        return [
            'debit' => (float) ($sums->total_debit ?? 0),
            'credit' => (float) ($sums->total_credit ?? 0),
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function formatBalanceRow(
        ChartOfAccount $account,
        float $opening,
        float $periodDebit,
        float $periodCredit,
        float $closing,
    ): array {
        return [
            'account_id' => $account->id,
            'account_code' => $account->code,
            'account_name' => $account->name,
            'type' => $account->type->value,
            'opening_debit' => $opening > 0 ? round($opening, 2) : 0.0,
            'opening_credit' => $opening < 0 ? round(abs($opening), 2) : 0.0,
            'period_debit' => round($periodDebit, 2),
            'period_credit' => round($periodCredit, 2),
            'closing_debit' => $closing > 0 ? round($closing, 2) : 0.0,
            'closing_credit' => $closing < 0 ? round(abs($closing), 2) : 0.0,
        ];
    }

    private function isZeroBalanceRow(float $opening, float $period, float $closing): bool
    {
        return abs($opening) < 0.005 && abs($period) < 0.005 && abs($closing) < 0.005;
    }

    /**
     * @param  array<string, mixed>  $filters
     * @return array<string, mixed>
     */
    private function normalizeFilters(array $filters, ?string $dateFrom, ?string $dateTo): array
    {
        return [
            'date_from' => $dateFrom ?? ($filters['date_from'] ?? null),
            'date_to' => $dateTo ?? ($filters['date_to'] ?? null),
            'branch_id' => $filters['branch_id'] ?? null,
            'legal_entity_id' => $filters['legal_entity_id'] ?? null,
            'fiscal_year_id' => $filters['fiscal_year_id'] ?? null,
            'cost_centre_id' => $filters['cost_centre_id'] ?? null,
            'account_id' => $filters['account_id'] ?? null,
            'warehouse_id' => $filters['warehouse_id'] ?? null,
        ];
    }

    /**
     * @param  array<string, mixed>  $filters
     */
    private function dateFrom(array $filters): string
    {
        return (string) ($filters['date_from'] ?? now()->startOfMonth()->toDateString());
    }

    /**
     * @param  array<string, mixed>  $filters
     */
    private function dateTo(array $filters): string
    {
        return (string) ($filters['date_to'] ?? now()->toDateString());
    }

    /**
     * @param  array<string, mixed>  $filters
     * @return Collection<int, int>
     */
    private function resolveCashAccountIds(array $filters = []): Collection
    {
        $mappingQuery = AccountMapping::query()
            ->whereIn('mapping_key', ['cash_on_hand', 'bank_account', 'petty_cash'])
            ->where('status', 'active');

        if (! empty($filters['branch_id'])) {
            $branchId = (int) $filters['branch_id'];
            $mappingQuery->where(function (Builder $q) use ($branchId) {
                $q->where('branch_id', $branchId)
                    ->orWhereNull('branch_id');
            });
        }

        if (! empty($filters['legal_entity_id'])) {
            $legalEntityId = (int) $filters['legal_entity_id'];
            $mappingQuery->where(function (Builder $q) use ($legalEntityId) {
                $q->where('legal_entity_id', $legalEntityId)
                    ->orWhereNull('legal_entity_id');
            });
        }

        $mapped = $mappingQuery->pluck('account_id');

        $typed = ChartOfAccount::query()
            ->where('type', ChartOfAccountType::Asset)
            ->where('status', 'active')
            ->where(function (Builder $q) {
                $q->where('name', 'like', '%cash%')
                    ->orWhere('name', 'like', '%bank%');
            })
            ->pluck('id');

        return $mapped->merge($typed)->unique()->values();
    }

    /**
     * @return Collection<int, int>
     */
    private function resolveBankAccountIds(): Collection
    {
        return AccountMapping::query()
            ->where('mapping_key', 'bank_account')
            ->where('status', 'active')
            ->pluck('account_id')
            ->unique()
            ->values();
    }

    private function classifyCashFlowCategory(?string $accountType): string
    {
        return match ($accountType) {
            ChartOfAccountType::Asset->value => 'investing',
            ChartOfAccountType::Equity->value => 'financing',
            default => 'operating',
        };
    }
}
