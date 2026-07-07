<?php

declare(strict_types=1);

namespace App\Http\Controllers\Admin;

use App\DTOs\Accounting\MatchBankStatementData;
use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\Accounting\ImportBankStatementRequest;
use App\Http\Requests\Admin\Accounting\MatchBankStatementLineRequest;
use App\Models\BankAccount;
use App\Models\BankStatementLine;
use App\Services\Accounting\BankReconciliationPageService;
use App\Services\Accounting\BankReconciliationService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

final class BankReconciliationController extends Controller
{
    public function __construct(
        private readonly BankReconciliationPageService $reconciliationPage,
        private readonly BankReconciliationService $reconciliation,
    ) {}

    public function index(Request $request): Response
    {
        $this->authorize('viewAny', BankAccount::class);

        $bankAccountId = $request->integer('bank_account_id') ?: null;

        return Inertia::render(
            'Admin/Accounting/Reconciliation/Index',
            $this->reconciliationPage->indexPayload($bankAccountId),
        );
    }

    public function import(ImportBankStatementRequest $request, BankAccount $bankAccount): RedirectResponse
    {
        $this->authorize('create', BankAccount::class);

        $result = $this->reconciliation->importCsv($bankAccount, $request->file('file'));

        return back()->with('success', __('Imported :count statement line(s). Skipped :skipped duplicate(s).', [
            'count' => $result['imported'],
            'skipped' => $result['skipped'],
        ]));
    }

    public function match(MatchBankStatementLineRequest $request, BankStatementLine $bankStatementLine): RedirectResponse
    {
        $this->authorize('viewAny', BankAccount::class);

        $data = MatchBankStatementData::fromRequest($request);

        $this->reconciliationPage->match(
            $bankStatementLine,
            $data->journalTransactionId,
            $data->matchedAmount,
            (int) $request->user()->id,
        );

        return back()->with('success', __('Statement line matched successfully.'));
    }
}
