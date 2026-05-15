<?php

declare(strict_types=1);

namespace App\DTOs\StockTransfer;

use App\Http\Requests\Admin\StoreStockTransferRequest;

final readonly class CreateStockTransferData
{
    /**
     * @param  list<TransferLineData>  $lines
     */
    public function __construct(
        public int $fromWarehouseId,
        public int $toWarehouseId,
        public array $lines,
        public ?int $userId,
        public ?string $notes,
    ) {}

    public static function fromRequest(StoreStockTransferRequest $request): self
    {
        $lines = collect($request->validated('lines'))
            ->map(fn (array $line) => new TransferLineData(
                variantId: (int) $line['product_variant_id'],
                batchId: isset($line['batch_id']) ? (int) $line['batch_id'] : null,
                quantity: (int) $line['quantity'],
            ))
            ->all();

        return new self(
            fromWarehouseId: (int) $request->validated('from_warehouse_id'),
            toWarehouseId: (int) $request->validated('to_warehouse_id'),
            lines: $lines,
            userId: $request->user()?->id,
            notes: $request->validated('notes'),
        );
    }
}
