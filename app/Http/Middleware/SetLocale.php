<?php

declare(strict_types=1);

namespace App\Http\Middleware;

use App\Services\LocaleService;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

final class SetLocale
{
    public function __construct(
        private readonly LocaleService $locales,
    ) {}

    public function handle(Request $request, Closure $next): Response
    {
        $this->locales->resolve($request);

        return $next($request);
    }
}
