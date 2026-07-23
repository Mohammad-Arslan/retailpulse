<?php

declare(strict_types=1);

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\SwitchBranchRequest;
use App\Services\BranchContextService;
use Illuminate\Http\RedirectResponse;

final class BranchContextController extends Controller
{
    public function __construct(
        private readonly BranchContextService $branchContext,
    ) {}

    public function update(SwitchBranchRequest $request): RedirectResponse
    {
        $user = $request->user();

        if ($user === null) {
            abort(403);
        }

        $branchId = $request->validated('branch_id');

        $this->branchContext->switchBranch(
            $request,
            $user,
            $branchId !== null ? (int) $branchId : null,
        );

        return back();
    }
}
