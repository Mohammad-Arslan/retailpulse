<?php

declare(strict_types=1);

namespace App\Http\Controllers\Admin;

use App\DTOs\Branch\CreateBranchData;
use App\DTOs\Branch\UpdateBranchData;
use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\StoreBranchRequest;
use App\Http\Requests\Admin\UpdateBranchRequest;
use App\Models\Branch;
use App\Repositories\Contracts\BranchRepositoryInterface;
use App\Services\BranchContextService;
use App\Services\BranchService;
use App\Support\OperatingHours;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

final class BranchController extends Controller
{
    public function __construct(
        private readonly BranchRepositoryInterface $branches,
        private readonly BranchService $branchService,
        private readonly BranchContextService $branchContext,
    ) {}

    public function index(Request $request): Response
    {
        $this->authorize('viewAny', Branch::class);

        $accessibleIds = $this->branchContext->accessibleBranchIds($request->user());

        return Inertia::render('Admin/Branches/Index', [
            'branches' => $this->branches->paginate(
                $request->only('search', 'is_active', 'sort', 'direction'),
                $accessibleIds,
            ),
            'filters' => $request->only('search', 'is_active', 'sort', 'direction'),
        ]);
    }

    public function create(): Response
    {
        $this->authorize('create', Branch::class);

        return Inertia::render('Admin/Branches/Create', [
            'defaultOperatingHours' => OperatingHours::defaults(),
            'timezones' => $this->commonTimezones(),
        ]);
    }

    public function store(StoreBranchRequest $request): RedirectResponse
    {
        $this->authorize('create', Branch::class);

        $branch = $this->branchService->create(CreateBranchData::fromRequest($request));

        return redirect()
            ->route('admin.branches.edit', $branch)
            ->with('success', __('Branch created successfully.'));
    }

    public function edit(Branch $branch): Response
    {
        $this->authorize('update', $branch);

        $branch->load('warehouses');

        return Inertia::render('Admin/Branches/Edit', [
            'branch' => [
                'id' => $branch->id,
                'name' => $branch->name,
                'code' => $branch->code,
                'address' => $branch->address,
                'currency' => $branch->currency,
                'timezone' => $branch->timezone,
                'operating_hours' => $branch->operating_hours ?? OperatingHours::defaults(),
                'receipt_footer' => $branch->receipt_footer,
                'is_active' => $branch->is_active,
                'warehouses' => $branch->warehouses->map(fn ($w) => [
                    'id' => $w->id,
                    'name' => $w->name,
                    'code' => $w->code,
                    'is_default' => $w->is_default,
                ]),
                'default_warehouse_id' => $branch->warehouses->firstWhere('is_default', true)?->id,
            ],
            'timezones' => $this->commonTimezones(),
        ]);
    }

    public function update(UpdateBranchRequest $request, Branch $branch): RedirectResponse
    {
        $this->authorize('update', $branch);

        $this->branchService->update($branch, UpdateBranchData::fromRequest($request));

        return redirect()
            ->route('admin.branches.edit', $branch)
            ->with('success', __('Branch updated successfully.'));
    }

    public function destroy(Branch $branch): RedirectResponse
    {
        $this->authorize('delete', $branch);

        $this->branchService->delete($branch);

        return redirect()
            ->route('admin.branches.index')
            ->with('success', __('Branch deleted successfully.'));
    }

    /**
     * @return list<string>
     */
    private function commonTimezones(): array
    {
        return [
            'UTC',
            'America/New_York',
            'America/Chicago',
            'America/Denver',
            'America/Los_Angeles',
            'Europe/London',
            'Europe/Paris',
            'Asia/Dubai',
            'Asia/Karachi',
            'Asia/Kolkata',
            'Asia/Singapore',
            'Australia/Sydney',
        ];
    }
}
