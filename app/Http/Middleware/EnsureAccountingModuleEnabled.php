<?php

declare(strict_types=1);

namespace App\Http\Middleware;

use App\Services\Accounting\Contracts\AccountingModuleGate;
use App\Support\BranchContext;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

final class EnsureAccountingModuleEnabled
{
    public function __construct(
        private readonly AccountingModuleGate $moduleGate,
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
