<?php

declare(strict_types=1);

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\CoaImportBatch;
use App\Models\OpeningBalanceImportBatch;
use App\Models\OpeningBalanceReconciliation;
use App\Services\Accounting\CoaImportService;
use App\Services\Accounting\OpeningBalanceImportService;
use DomainException;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

final class AccountingImportController extends Controller
{
    public function __construct(
        private readonly CoaImportService $coaImportService,
        private readonly OpeningBalanceImportService $openingBalanceImportService,
    ) {}

    public function approveCoaBatch(Request $request, CoaImportBatch $batch): RedirectResponse
    {
        abort_unless($request->user()?->can('accounting.import-coa'), 403);

        try {
            $this->coaImportService->approveBatch($batch, (int) $request->user()->id);
        } catch (DomainException $e) {
            return back()->withErrors(['import' => $e->getMessage()]);
        }

        return back()->with('success', __('COA import batch approved.'));
    }

    public function approveOpeningBalanceBatch(Request $request, OpeningBalanceImportBatch $batch): RedirectResponse
    {
        abort_unless($request->user()?->can('accounting.import-opening-balances'), 403);

        try {
            $this->openingBalanceImportService->approveBatch(
                $batch,
                (int) $request->user()->id,
                $request->has('variance_tolerance') ? (float) $request->input('variance_tolerance') : null,
            );
        } catch (DomainException $e) {
            return back()->withErrors(['import' => $e->getMessage()]);
        }

        return back()->with('success', __('Opening balance import batch approved.'));
    }

    public function approveOpeningBalanceVariance(
        Request $request,
        OpeningBalanceReconciliation $reconciliation,
    ): RedirectResponse {
        abort_unless($request->user()?->can('accounting.import-opening-balances'), 403);

        $this->openingBalanceImportService->approveVariance($reconciliation, (int) $request->user()->id);

        return back()->with('success', __('Reconciliation variance approved.'));
    }
}
