<?php

declare(strict_types=1);

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;

final class EnsureLocalEnvironment
{
    public function handle(Request $request, Closure $next): Response
    {
        if (! app()->environment('local')) {
            throw new NotFoundHttpException;
        }

        return $next($request);
    }
}
