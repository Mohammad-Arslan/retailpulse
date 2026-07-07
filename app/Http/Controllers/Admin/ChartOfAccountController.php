<?php

declare(strict_types=1);

namespace App\Http\Controllers\Admin;

use App\DTOs\Accounting\CreateChartOfAccountData;
use App\DTOs\Accounting\UpdateChartOfAccountData;
use App\Enums\ChartOfAccountType;
use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\Accounting\StoreChartOfAccountRequest;
use App\Http\Requests\Admin\Accounting\UpdateChartOfAccountRequest;
use App\Models\Branch;
use App\Models\ChartOfAccount;
use App\Services\Accounting\ChartOfAccountService;
use App\Support\ListPagination;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

final class ChartOfAccountController extends Controller
{
    public function __construct(
        private readonly ChartOfAccountService $chartOfAccountService,
    ) {}

    public function index(Request $request): Response
    {
        $this->authorize('viewAny', ChartOfAccount::class);

        $filters = ListPagination::filters($request, ['search', 'type', 'status', 'sort', 'direction']);
        $perPage = ListPagination::resolve($filters['per_page']);

        return Inertia::render('Admin/Accounting/ChartOfAccounts/Index', [
            'accounts' => $this->chartOfAccountService->paginatedIndex($filters, $perPage, $request),
            'filters' => $filters,
            'parentOptions' => $this->chartOfAccountService->parentOptions(),
            'branches' => Branch::query()->orderBy('name')->get(['id', 'name']),
            'accountTypes' => ChartOfAccountType::values(),
        ]);
    }

    public function store(StoreChartOfAccountRequest $request): RedirectResponse
    {
        $this->authorize('create', ChartOfAccount::class);

        $this->chartOfAccountService->create(
            CreateChartOfAccountData::fromRequest($request),
            (int) $request->user()->id,
        );

        return back()->with('success', __('Account created successfully.'));
    }

    public function update(UpdateChartOfAccountRequest $request, ChartOfAccount $chartOfAccount): RedirectResponse
    {
        $this->authorize('update', $chartOfAccount);

        $this->chartOfAccountService->update(
            $chartOfAccount,
            UpdateChartOfAccountData::fromRequest($request),
            (int) $request->user()->id,
        );

        return back()->with('success', __('Account updated successfully.'));
    }
}
