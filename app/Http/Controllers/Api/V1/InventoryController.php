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
        $canSell = collect($results)->every(fn (array $line) => $line['can_sell']);

        return response()->json([
            'can_sell' => $canSell,
            'sufficient' => $canSell,
            'lines' => $results,
        ], $canSell ? 200 : 422);
    }
}
