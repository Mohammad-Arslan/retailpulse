<?php

declare(strict_types=1);

namespace App\DTOs\Inventory;

use App\Http\Requests\Admin\ReceiveStockRequest;

final readonly class ReceiveStockData
{
    public function __construct(
        public int $warehouseId,
        public int $variantId,
        public ?int $batchId,
        public int $quantity,
        public ?int $userId,
        public ?string $notes,
    ) {}

    public static function fromRequest(ReceiveStockRequest $request): self
    {
        return new self(
            warehouseId: (int) $request->validated('warehouse_id'),
            variantId: (int) $request->validated('product_variant_id'),
            batchId: $request->validated('batch_id'),
            quantity: (int) $request->validated('quantity'),
            userId: $request->user()?->id,
            notes: $request->validated('notes'),
        );
    }
}
