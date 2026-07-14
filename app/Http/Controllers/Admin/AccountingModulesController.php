<?php

declare(strict_types=1);

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\Accounting\UpdateBranchAccountingModulesRequest;
use App\Models\Branch;
use App\Models\BranchAccountingProfile;
use App\Services\Accounting\BranchAccountingModuleService;
use App\Services\Accounting\Contracts\AccountingModuleGate as AccountingModuleGateContract;
use App\Support\BranchContext;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

final class AccountingModulesController extends Controller
{
    public function __construct(
        private readonly BranchAccountingModuleService $moduleService,
        private readonly AccountingModuleGateContract $moduleGate,
    ) {}

    public function index(Request $request): Response
    {
        $this->authorize('viewAny', BranchAccountingProfile::class);

        $context = app(BranchContext::class);
        $branches = Branch::query()
            ->where('is_active', true)
            ->when(
                $context->isRestricted(),
                fn ($q) => $q->whereIn('id', $context->accessibleBranchIds ?? []),
            )
            ->orderBy('name')
            ->get(['id', 'name', 'code']);

        $requestedBranchId = $request->integer('branch_id') ?: null;
        $selectedBranchId = null;

        if ($requestedBranchId && $context->canAccessBranch($requestedBranchId)) {
            $selectedBranchId = $requestedBranchId;
        } elseif ($context->branchId !== null && $context->canAccessBranch($context->branchId)) {
            $selectedBranchId = $context->branchId;
        } elseif ($branches->count() === 1) {
            $selectedBranchId = (int) $branches->first()->id;
        }

        $storedModules = $selectedBranchId !== null
            ? $this->moduleService->storedModules($selectedBranchId)
            : ['core'];

        $resolvedModules = $selectedBranchId !== null
            ? $this->moduleGate->enabledModules($selectedBranchId)
            : ['core'];

        return Inertia::render('Admin/Accounting/Modules/Index', [
            'branches' => $branches,
            'selectedBranchId' => $selectedBranchId,
            'modules' => $this->moduleService->moduleCatalog(),
            'enabledModules' => $storedModules,
            'resolvedModules' => $resolvedModules,
            'requiresBranchSelection' => $selectedBranchId === null,
        ]);
    }

    public function update(UpdateBranchAccountingModulesRequest $request): RedirectResponse
    {
        $this->authorize('viewAny', BranchAccountingProfile::class);

        $branchId = (int) $request->validated('branch_id');
        $context = app(BranchContext::class);

        if (! $context->canAccessBranch($branchId)) {
            abort(403);
        }

        $this->moduleService->updateModules(
            $branchId,
            array_values($request->validated('modules')),
        );

        return redirect()
            ->route('admin.accounting.modules.index', ['branch_id' => $branchId])
            ->with('success', __('Accounting modules updated for the selected branch.'));
    }
}
