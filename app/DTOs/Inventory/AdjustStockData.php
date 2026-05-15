<?php

declare(strict_types=1);

namespace App\DTOs\Inventory;

use App\Enums\StockMovementReason;
use App\Http\Requests\Admin\AdjustStockRequest;

final readonly class AdjustStockData
{
    public function __construct(
        public int $warehouseId,
        public int $variantId,
        public ?int $batchId,
        public int $quantity,
        public StockMovementReason $reason,
        public ?int $userId,
        public ?string $notes,
    ) {}

    public static function fromRequest(AdjustStockRequest $request): self
    {
        return new self(
            warehouseId: (int) $request->validated('warehouse_id'),
            variantId: (int) $request->validated('product_variant_id'),
            batchId: $request->validated('batch_id'),
            quantity: (int) $request->validated('quantity'),
            reason: StockMovementReason::from($request->validated('reason')),
            userId: $request->user()?->id,
            notes: $request->validated('notes'),
        );
    }
}
