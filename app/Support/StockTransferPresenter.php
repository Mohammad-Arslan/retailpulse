<?php

declare(strict_types=1);

namespace App\Support;

use App\Models\StockTransfer;

final class StockTransferPresenter
{
    /**
     * @return array<string, mixed>
     */
    public static function summary(StockTransfer $transfer): array
    {
        $transfer->loadMissing(['fromWarehouse', 'toWarehouse']);

        return [
            'id' => $transfer->id,
            'reference_no' => $transfer->reference_no,
            'status' => $transfer->status->value,
            'from_warehouse' => $transfer->fromWarehouse->only('id', 'name', 'code'),
            'to_warehouse' => $transfer->toWarehouse->only('id', 'name', 'code'),
            'items_count' => $transfer->items_count ?? $transfer->items()->count(),
            'created_at' => $transfer->created_at?->toIso8601String(),
            'shipped_at' => $transfer->shipped_at?->toIso8601String(),
            'received_at' => $transfer->received_at?->toIso8601String(),
        ];
    }

    /**
     * @return array<string, mixed>
     */
    public static function detail(StockTransfer $transfer): array
    {
        $transfer->loadMissing([
            'fromWarehouse.branch',
            'toWarehouse.branch',
            'items.variant.product',
            'items.batch',
            'creator',
            'shipper',
            'receiver',
        ]);

        return [
            ...self::summary($transfer),
            'notes' => $transfer->notes,
            'from_warehouse' => [
                ...$transfer->fromWarehouse->only('id', 'name', 'code'),
                'branch' => $transfer->fromWarehouse->branch?->only('id', 'name'),
            ],
            'to_warehouse' => [
                ...$transfer->toWarehouse->only('id', 'name', 'code'),
                'branch' => $transfer->toWarehouse->branch?->only('id', 'name'),
            ],
            'items' => $transfer->items->map(fn ($item) => [
                'id' => $item->id,
                'quantity' => $item->quantity,
                'variant' => [
                    'id' => $item->variant->id,
                    'sku' => $item->variant->sku,
                    'name' => $item->variant->displayName(),
                    'product_name' => $item->variant->product?->name,
                ],
                'batch' => $item->batch ? [
                    'id' => $item->batch->id,
                    'batch_no' => $item->batch->batch_no,
                    'expiry_date' => $item->batch->expiry_date?->toDateString(),
                ] : null,
            ])->values()->all(),
            'creator' => $transfer->creator?->only('id', 'name'),
            'shipper' => $transfer->shipper?->only('id', 'name'),
            'receiver' => $transfer->receiver?->only('id', 'name'),
        ];
    }
}
