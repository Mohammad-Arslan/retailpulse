<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1\Pos;

use App\Http\Controllers\Controller;
use App\Services\Pos\PosCatalogFilterService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

final class PosCatalogFiltersController extends Controller
{
    public function __construct(
        private readonly PosCatalogFilterService $filters,
    ) {}

    public function __invoke(Request $request): JsonResponse
    {
        $request->validate([
            'branch_id' => ['required', 'integer', 'exists:branches,id'],
        ]);

        $branchId = (int) $request->input('branch_id');

        return response()->json([
            'categories' => $this->filters->categoriesForBranch($branchId),
            'brands' => $this->filters->brandsForBranch($branchId),
        ]);
    }
}
