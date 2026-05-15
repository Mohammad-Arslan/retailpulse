<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Requests\Api\V1\CheckStockAvailabilityRequest;
use App\Services\InventoryService;
use Illuminate\Http\JsonResponse;

final class InventoryController extends Controller
{
    public function __construct(
        private readonly InventoryService $inventory,
    ) {}

    public function checkAvailability(CheckStockAvailabilityRequest $request): JsonResponse
    {
        $warehouseId = (int) $request->validated('warehouse_id');
        $lines = $request->validated('lines');

        $results = $this->inventory->checkAvailability($warehouseId, $lines);
        $sufficient = collect($results)->every(fn (array $line) => $line['sufficient']);

        return response()->json([
            'sufficient' => $sufficient,
            'lines' => $results,
        ], $sufficient ? 200 : 422);
    }
}
