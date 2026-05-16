<?php

declare(strict_types=1);

namespace App\DTOs\Inventory;

use App\Http\Requests\Admin\ReceiveStockRequest;

final readonly class ReceiveStockData
{
    /**
     * @param  list<string>  $serialNumbers
     */
    public function __construct(
        public int $warehouseId,
        public int $variantId,
        public ?int $batchId,
        public int $quantity,
        public ?int $userId,
        public ?string $notes,
        public array $serialNumbers = [],
    ) {}

    public static function fromRequest(ReceiveStockRequest $request): self
    {
        $serials = array_values(array_filter(
            array_map('trim', $request->validated('serial_numbers', [])),
            static fn (string $value): bool => $value !== '',
        ));

        $quantity = (int) $request->validated('quantity');

        if ($serials !== []) {
            $quantity = count($serials);
        }

        return new self(
            warehouseId: (int) $request->validated('warehouse_id'),
            variantId: (int) $request->validated('product_variant_id'),
            batchId: $request->validated('batch_id'),
            quantity: $quantity,
            userId: $request->user()?->id,
            notes: $request->validated('notes'),
            serialNumbers: $serials,
        );
    }
}
