<?php

declare(strict_types=1);

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

final class EnsurePosAccess
{
    public function handle(Request $request, Closure $next): Response
    {
        if (! $request->user()?->can('pos.access')) {
            abort(403, 'You do not have access to the POS.');
        }

        return $next($request);
    }
}
