<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\Pos;

use App\DTOs\Inventory\DeductStockData;
use App\Enums\StockMovementReason;
use App\Http\Controllers\Controller;
use App\Http\Requests\Api\Pos\PosStockCheckRequest;
use App\Http\Requests\Api\Pos\PosStockDeductRequest;
use App\Services\InventoryService;
use Illuminate\Http\JsonResponse;

final class InventoryController extends Controller
{
    public function __construct(
        private readonly InventoryService $inventory,
    ) {}

    public function stockCheck(PosStockCheckRequest $request): JsonResponse
    {
        $warehouseId = (int) $request->validated('warehouse_id');
        $lines = $request->validated('lines');

        $results = $this->inventory->checkAvailability($warehouseId, $lines);
        $canSell = collect($results)->every(fn (array $line) => $line['can_sell']);

        return response()->json([
            'can_sell' => $canSell,
            'lines' => $results,
        ], $canSell ? 200 : 422);
    }

    public function stockDeduct(PosStockDeductRequest $request): JsonResponse
    {
        $validated = $request->validated();
        $warehouseId = (int) $validated['warehouse_id'];

        $inventory = $this->inventory->deduct(new DeductStockData(
            warehouseId: $warehouseId,
            variantId: (int) $validated['variant_id'],
            batchId: isset($validated['batch_id']) ? (int) $validated['batch_id'] : null,
            quantity: (int) $validated['quantity'],
            reason: StockMovementReason::Sale,
            userId: $request->user()?->id,
            referenceType: $validated['reference_type'] ?? null,
            referenceId: isset($validated['reference_id']) ? (int) $validated['reference_id'] : null,
            notes: $validated['notes'] ?? null,
        ));

        return response()->json([
            'inventory_id' => $inventory->id,
            'quantity_on_hand' => $inventory->quantity_on_hand,
            'quantity_reserved' => $inventory->quantity_reserved,
            'available' => $inventory->availableQuantity(),
        ]);
    }
}
