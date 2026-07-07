<?php

declare(strict_types=1);

namespace App\Http\Controllers\Admin;

use App\DTOs\Accounting\CreateFiscalYearData;
use App\DTOs\Accounting\FiscalYearReopenData;
use App\DTOs\Accounting\UpdateFinancialSettingsData;
use App\DTOs\Accounting\UpdateFiscalYearData;
use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\Accounting\FiscalYearReopenRequest;
use App\Http\Requests\Admin\Accounting\StoreFiscalYearRequest;
use App\Http\Requests\Admin\Accounting\UpdateFinancialSettingsRequest;
use App\Http\Requests\Admin\Accounting\UpdateFiscalYearRequest;
use App\Models\FiscalYear;
use App\Models\FiscalYearReopenRequest as FiscalYearReopenRequestModel;
use App\Services\Accounting\AccountingSettingsPageService;
use App\Services\Accounting\FiscalCloseService;
use App\Services\Accounting\FiscalYearService;
use DomainException;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

final class AccountingSettingsController extends Controller
{
    public function __construct(
        private readonly AccountingSettingsPageService $settingsPageService,
        private readonly FiscalYearService $fiscalYearService,
        private readonly FiscalCloseService $fiscalCloseService,
    ) {}

    public function index(Request $request): Response
    {
        abort_unless(
            $request->user()?->can('accounting.view') || $request->user()?->can('accounting.manage-fiscal-years'),
            403,
        );

        return Inertia::render('Admin/Accounting/Settings/Index', $this->settingsPageService->indexPayload());
    }

    public function update(UpdateFinancialSettingsRequest $request): RedirectResponse
    {
        abort_unless($request->user()?->can('accounting.manage-fiscal-years'), 403);

        $this->settingsPageService->updateSettings(UpdateFinancialSettingsData::fromRequest($request));

        return back()->with('success', __('Financial settings updated successfully.'));
    }

    public function storeFiscalYear(StoreFiscalYearRequest $request): RedirectResponse
    {
        abort_unless($request->user()?->can('accounting.manage-fiscal-years'), 403);

        $this->fiscalYearService->create(CreateFiscalYearData::fromRequest($request));

        return back()->with('success', __('Fiscal year created successfully.'));
    }

    public function updateFiscalYear(UpdateFiscalYearRequest $request, FiscalYear $fiscalYear): RedirectResponse
    {
        abort_unless($request->user()?->can('accounting.manage-fiscal-years'), 403);

        $this->fiscalYearService->update($fiscalYear, UpdateFiscalYearData::fromRequest($request));

        return back()->with('success', __('Fiscal year updated successfully.'));
    }

    public function closeFiscalYear(Request $request, FiscalYear $fiscalYear): RedirectResponse
    {
        abort_unless($request->user()?->can('accounting.close-fiscal-year'), 403);

        try {
            $this->fiscalCloseService->close($fiscalYear, (int) $request->user()->id);
        } catch (DomainException $e) {
            return back()->withErrors(['fiscal_year' => $e->getMessage()]);
        }

        return back()->with('success', __('Fiscal year closed successfully.'));
    }

    public function requestReopen(FiscalYearReopenRequest $request, FiscalYear $fiscalYear): RedirectResponse
    {
        try {
            $this->fiscalCloseService->requestReopen(
                $fiscalYear,
                (int) $request->user()->id,
                FiscalYearReopenData::fromRequest($request)->reason,
            );
        } catch (DomainException $e) {
            return back()->withErrors(['fiscal_year' => $e->getMessage()]);
        }

        return back()->with('success', __('Fiscal year reopen request submitted.'));
    }

    public function approveReopen(Request $request, FiscalYearReopenRequestModel $fiscalYearReopenRequest): RedirectResponse
    {
        abort_unless($request->user()?->can('accounting.reopen-fiscal-year'), 403);

        try {
            $this->fiscalCloseService->approveReopen($fiscalYearReopenRequest, (int) $request->user()->id);
        } catch (DomainException $e) {
            return back()->withErrors(['fiscal_year' => $e->getMessage()]);
        }

        return back()->with('success', __('Reopen approval recorded.'));
    }
}
