<?php

declare(strict_types=1);

namespace App\Support;

use App\Models\Inventory;

final class InventoryPresenter
{
    /**
     * @return array<string, mixed>
     */
    public static function row(Inventory $inventory): array
    {
        $inventory->loadMissing(['warehouse.branch', 'variant.product', 'batch']);

        return [
            'id' => $inventory->id,
            'warehouse' => [
                'id' => $inventory->warehouse->id,
                'name' => $inventory->warehouse->name,
                'code' => $inventory->warehouse->code,
                'branch' => $inventory->warehouse->branch?->only('id', 'name', 'code'),
            ],
            'variant' => [
                'id' => $inventory->variant->id,
                'sku' => $inventory->variant->sku,
                'name' => $inventory->variant->displayName(),
                'product_name' => $inventory->variant->product?->name,
            ],
            'batch' => $inventory->batch ? [
                'id' => $inventory->batch->id,
                'batch_no' => $inventory->batch->batch_no,
                'expiry_date' => $inventory->batch->expiry_date?->toDateString(),
            ] : null,
            'quantity_on_hand' => $inventory->quantity_on_hand,
            'quantity_reserved' => $inventory->quantity_reserved,
            'quantity_available' => $inventory->availableQuantity(),
        ];
    }
}
