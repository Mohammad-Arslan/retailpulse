<?php

declare(strict_types=1);

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Services\Accounting\AccountingReportPageService;
use App\Services\Accounting\FinancialReportingService;
use App\Support\BranchContext;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use Inertia\Inertia;
use Inertia\Response;
use Symfony\Component\HttpFoundation\StreamedResponse;

final class AccountingReportController extends Controller
{
    /**
     * @var array<string, array{method: string, title: string, exportFilename: string}>
     */
    private const REPORTS = [
        'trial-balance' => ['method' => 'trialBalance', 'title' => 'Trial Balance', 'exportFilename' => 'trial-balance'],
        'profit-and-loss' => ['method' => 'profitAndLoss', 'title' => 'Profit and Loss', 'exportFilename' => 'profit-and-loss'],
        'balance-sheet' => ['method' => 'balanceSheet', 'title' => 'Balance Sheet', 'exportFilename' => 'balance-sheet'],
        'general-ledger' => ['method' => 'generalLedger', 'title' => 'General Ledger', 'exportFilename' => 'general-ledger'],
        'cost-centre-pl' => ['method' => 'costCentreProfitAndLoss', 'title' => 'Cost Centre P&L', 'exportFilename' => 'cost-centre-pl'],
        'cash-flow' => ['method' => 'cashFlow', 'title' => 'Cash Flow Statement', 'exportFilename' => 'cash-flow'],
        'ar-aging' => ['method' => 'arAging', 'title' => 'AR Aging', 'exportFilename' => 'ar-aging'],
        'ap-aging' => ['method' => 'apAging', 'title' => 'AP Aging', 'exportFilename' => 'ap-aging'],
        'bank-book' => ['method' => 'bankBook', 'title' => 'Bank Book', 'exportFilename' => 'bank-book'],
        'inventory-valuation' => ['method' => 'inventoryValuation', 'title' => 'Inventory Valuation', 'exportFilename' => 'inventory-valuation'],
        'asset-register' => ['method' => 'assetRegister', 'title' => 'Asset Register', 'exportFilename' => 'asset-register'],
        'fx-revaluation' => ['method' => 'fxRevaluation', 'title' => 'FX Revaluation', 'exportFilename' => 'fx-revaluation'],
        'petty-cash' => ['method' => 'pettyCash', 'title' => 'Petty Cash', 'exportFilename' => 'petty-cash'],
        'cheque-status' => ['method' => 'chequeStatus', 'title' => 'Cheque Status', 'exportFilename' => 'cheque-status'],
        'audit-trail' => ['method' => 'auditTrail', 'title' => 'Audit Trail', 'exportFilename' => 'audit-trail'],
        'unposted-journals' => ['method' => 'unpostedJournals', 'title' => 'Unposted Journals', 'exportFilename' => 'unposted-journals'],
        'journal-register' => ['method' => 'journalRegister', 'title' => 'Journal Register', 'exportFilename' => 'journal-register'],
    ];

    public function __construct(
        private readonly FinancialReportingService $reports,
        private readonly AccountingReportPageService $reportPageService,
    ) {}

    public function index(Request $request): Response
    {
        abort_unless(
            $request->user()?->can('accounting.view-reports') || $request->user()?->can('accounting.view'),
            403,
        );

        $reportCards = [
            ['key' => 'trialBalance', 'route' => 'admin.accounting.reports.trial-balance', 'available' => true],
            ['key' => 'profitAndLoss', 'route' => 'admin.accounting.reports.profit-and-loss', 'available' => true],
            ['key' => 'balanceSheet', 'route' => 'admin.accounting.reports.balance-sheet', 'available' => true],
            ['key' => 'generalLedger', 'route' => 'admin.accounting.reports.general-ledger', 'available' => true],
            ['key' => 'costCentrePl', 'route' => 'admin.accounting.reports.cost-centre-pl', 'available' => true],
            ['key' => 'cashFlow', 'route' => 'admin.accounting.reports.cash-flow', 'available' => true],
            ['key' => 'arAging', 'route' => 'admin.accounting.reports.ar-aging', 'available' => true],
            ['key' => 'apAging', 'route' => 'admin.accounting.reports.ap-aging', 'available' => true],
            ['key' => 'bankBook', 'route' => 'admin.accounting.reports.bank-book', 'available' => true],
            ['key' => 'inventoryValuation', 'route' => 'admin.accounting.reports.inventory-valuation', 'available' => true],
            ['key' => 'assetRegister', 'route' => 'admin.accounting.reports.asset-register', 'available' => true],
            ['key' => 'fxRevaluation', 'route' => 'admin.accounting.reports.fx-revaluation', 'available' => true],
            ['key' => 'pettyCash', 'route' => 'admin.accounting.reports.petty-cash', 'available' => true],
            ['key' => 'chequeStatus', 'route' => 'admin.accounting.reports.cheque-status', 'available' => true],
            ['key' => 'auditTrail', 'route' => 'admin.accounting.reports.audit-trail', 'available' => true],
            ['key' => 'unpostedJournals', 'route' => 'admin.accounting.reports.unposted-journals', 'available' => true],
            ['key' => 'journalRegister', 'route' => 'admin.accounting.reports.journal-register', 'available' => true],
        ];

        return Inertia::render('Admin/Accounting/Reports/Index', [
            'reports' => $reportCards,
        ]);
    }

    public function trialBalance(Request $request): Response
    {
        return $this->renderReport($request, 'trial-balance');
    }

    public function profitAndLoss(Request $request): Response
    {
        return $this->renderReport($request, 'profit-and-loss');
    }

    public function balanceSheet(Request $request): Response
    {
        return $this->renderReport($request, 'balance-sheet');
    }

    public function generalLedger(Request $request): Response
    {
        return $this->renderReport($request, 'general-ledger');
    }

    public function costCentrePl(Request $request): Response
    {
        return $this->renderReport($request, 'cost-centre-pl');
    }

    public function cashFlow(Request $request): Response
    {
        return $this->renderReport($request, 'cash-flow');
    }

    public function arAging(Request $request): Response
    {
        return $this->renderReport($request, 'ar-aging');
    }

    public function apAging(Request $request): Response
    {
        return $this->renderReport($request, 'ap-aging');
    }

    public function bankBook(Request $request): Response
    {
        return $this->renderReport($request, 'bank-book');
    }

    public function inventoryValuation(Request $request): Response
    {
        return $this->renderReport($request, 'inventory-valuation');
    }

    public function assetRegister(Request $request): Response
    {
        return $this->renderReport($request, 'asset-register');
    }

    public function fxRevaluation(Request $request): Response
    {
        return $this->renderReport($request, 'fx-revaluation');
    }

    public function pettyCash(Request $request): Response
    {
        return $this->renderReport($request, 'petty-cash');
    }

    public function chequeStatus(Request $request): Response
    {
        return $this->renderReport($request, 'cheque-status');
    }

    public function auditTrail(Request $request): Response
    {
        return $this->renderReport($request, 'audit-trail');
    }

    public function unpostedJournals(Request $request): Response
    {
        return $this->renderReport($request, 'unposted-journals');
    }

    public function journalRegister(Request $request): Response
    {
        return $this->renderReport($request, 'journal-register');
    }

    public function export(Request $request, string $reportKey): StreamedResponse
    {
        abort_unless($request->user()?->can('accounting.export-reports'), 403);

        $config = self::REPORTS[$reportKey] ?? abort(404);
        $filters = $this->resolveFilters($request);
        $data = $this->reports->{$config['method']}($filters);
        $csvRows = $this->reports->toCsvRows($reportKey, $data);
        $filename = $config['exportFilename'].'-'.now()->format('Ymd-His').'.csv';

        return response()->streamDownload(function () use ($csvRows) {
            $handle = fopen('php://output', 'w');

            foreach ($csvRows as $row) {
                fputcsv($handle, $row);
            }

            fclose($handle);
        }, $filename, ['Content-Type' => 'text/csv']);
    }

    private function renderReport(Request $request, string $reportKey): Response
    {
        abort_unless(
            $request->user()?->can('accounting.view-reports') || $request->user()?->can('accounting.view'),
            403,
        );

        $config = self::REPORTS[$reportKey] ?? abort(404);
        $filters = $this->resolveFilters($request);
        $data = $this->reports->{$config['method']}($filters);

        return Inertia::render('Admin/Accounting/Reports/Show', [
            'reportKey' => str_replace('-', '_', $reportKey),
            'reportCardKey' => Str::camel(str_replace('-', '_', $reportKey)),
            'reportSlug' => $reportKey,
            'title' => $config['title'],
            'filters' => $filters,
            'rows' => $data['rows'] ?? [],
            'totals' => $data['totals'] ?? null,
            ...$this->reportPageService->filterOptions(),
            'canExport' => $request->user()?->can('accounting.export-reports') ?? false,
        ]);
    }

    /**
     * @return array<string, mixed>
     */
    private function resolveFilters(Request $request): array
    {
        $context = app(BranchContext::class);
        $filters = $request->only([
            'date_from',
            'date_to',
            'branch_id',
            'legal_entity_id',
            'fiscal_year_id',
            'cost_centre_id',
            'account_id',
            'warehouse_id',
            'auditable_type',
            'event',
        ]);

        if ($context->branchId !== null && empty($filters['branch_id'])) {
            $filters['branch_id'] = $context->branchId;
        }

        if (empty($filters['date_from'])) {
            $filters['date_from'] = now()->startOfMonth()->toDateString();
        }

        if (empty($filters['date_to'])) {
            $filters['date_to'] = now()->toDateString();
        }

        return $filters;
    }
}
