<?php

declare(strict_types=1);

namespace App\Http\Controllers\Admin\Hr;

use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\Hr\UpdateBranchHrModulesRequest;
use App\Models\Branch;
use App\Models\BranchHrProfile;
use App\Services\Hr\BranchHrPayrollModuleService;
use App\Services\Hr\Contracts\HrPayrollModuleGate;
use App\Support\BranchContext;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

final class HrModulesController extends Controller
{
    public function __construct(
        private readonly BranchHrPayrollModuleService $moduleService,
        private readonly HrPayrollModuleGate $moduleGate,
    ) {}

    public function index(Request $request): Response
    {
        $this->authorize('viewAny', BranchHrProfile::class);

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
            : ['expenses', 'hr', 'holiday_calendar'];

        return Inertia::render('Admin/Hr/Modules/Index', [
            'branches' => $branches,
            'selectedBranchId' => $selectedBranchId,
            'modules' => $this->moduleService->moduleCatalog(),
            'enabledModules' => $storedModules,
            'resolvedModules' => $selectedBranchId !== null
                ? $this->moduleGate->enabledModules($selectedBranchId)
                : $storedModules,
            'requiresBranchSelection' => $selectedBranchId === null,
        ]);
    }

    public function update(UpdateBranchHrModulesRequest $request): RedirectResponse
    {
        $this->authorize('viewAny', BranchHrProfile::class);

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
            ->route('admin.hr.modules.index', ['branch_id' => $branchId])
            ->with('success', __('HR Modules Updated For The Selected Branch.'));
    }
}
