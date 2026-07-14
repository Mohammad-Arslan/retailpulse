<?php

declare(strict_types=1);

namespace App\Http\Middleware;

use App\Services\Hr\Contracts\HrPayrollModuleGate;
use App\Support\BranchContext;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

final class EnsureHrModuleEnabled
{
    public function __construct(
        private readonly HrPayrollModuleGate $moduleGate,
        private readonly BranchContext $branchContext,
    ) {}

    public function handle(Request $request, Closure $next, string $moduleKey): Response
    {
        if (! $this->moduleGate->isEnabled($moduleKey, $this->branchContext->branchId)) {
            abort(403);
        }

        return $next($request);
    }
}
