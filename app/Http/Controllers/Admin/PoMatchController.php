<?php

declare(strict_types=1);

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\PoMatchResult;
use App\Services\Procurement\ThreeWayMatchingService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

final class PoMatchController extends Controller
{
    public function __construct(
        private readonly ThreeWayMatchingService $matching,
    ) {}

    public function resolve(Request $request, PoMatchResult $poMatchResult): RedirectResponse
    {
        $this->authorize('resolve', $poMatchResult);

        $this->matching->resolveException($poMatchResult, (int) $request->user()->id);

        return back()->with('success', __('Match exception resolved.'));
    }
}
