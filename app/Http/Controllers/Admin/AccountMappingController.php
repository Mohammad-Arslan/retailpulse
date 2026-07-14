<?php

declare(strict_types=1);

namespace App\Http\Controllers\Admin;

use App\DTOs\Accounting\CreateAccountMappingData;
use App\DTOs\Accounting\UpdateAccountMappingData;
use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\Accounting\StoreAccountMappingRequest;
use App\Http\Requests\Admin\Accounting\UpdateAccountMappingRequest;
use App\Models\AccountMapping;
use App\Models\Branch;
use App\Services\Accounting\AccountMappingService;
use App\Support\AccountMappingKeys;
use App\Support\ListPagination;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

final class AccountMappingController extends Controller
{
    public function __construct(
        private readonly AccountMappingService $accountMappingService,
    ) {}

    public function index(Request $request): Response
    {
        $this->authorize('viewAny', AccountMapping::class);

        $filters = ListPagination::filters($request, ['search', 'mapping_key', 'status', 'sort', 'direction']);
        $perPage = ListPagination::resolve($filters['per_page']);

        return Inertia::render('Admin/Accounting/AccountMappings/Index', [
            'mappings' => $this->accountMappingService->paginateIndex($filters, $perPage),
            'filters' => $filters,
            'mappingKeys' => AccountMappingKeys::all(),
            'accounts' => $this->accountMappingService->postableAccountOptions(),
            'branches' => Branch::query()
                ->where('is_active', true)
                ->orderBy('name')
                ->get(['id', 'name']),
        ]);
    }

    public function store(StoreAccountMappingRequest $request): RedirectResponse
    {
        $this->authorize('create', AccountMapping::class);

        $this->accountMappingService->create(CreateAccountMappingData::fromRequest($request));

        return back()->with('success', __('Account mapping created successfully.'));
    }

    public function update(UpdateAccountMappingRequest $request, AccountMapping $accountMapping): RedirectResponse
    {
        $this->authorize('update', $accountMapping);

        $this->accountMappingService->update(
            $accountMapping,
            UpdateAccountMappingData::fromRequest($request),
        );

        return back()->with('success', __('Account mapping updated successfully.'));
    }

    public function destroy(AccountMapping $accountMapping): RedirectResponse
    {
        $this->authorize('delete', $accountMapping);

        $this->accountMappingService->delete($accountMapping);

        return back()->with('success', __('Account mapping deleted.'));
    }
}
