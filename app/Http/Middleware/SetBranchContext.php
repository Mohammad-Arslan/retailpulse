<?php

declare(strict_types=1);

namespace App\Http\Middleware;

use App\Services\BranchContextService;
use App\Support\BranchContext;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

final class SetBranchContext
{
    public function __construct(
        private readonly BranchContextService $branchContext,
    ) {}

    public function handle(Request $request, Closure $next): Response
    {
        $user = $request->user();

        if ($user !== null) {
            $this->branchContext->initializeSession($request, $user);
        }

        $context = $this->branchContext->resolve($request);

        app()->instance(BranchContext::class, $context);

        return $next($request);
    }
}
